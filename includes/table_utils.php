<?php
/**
 * Table Utilities for pagination, sorting, and filtering
 */

/**
 * Builds WHERE conditions for SQL queries
 * 
 * @param array $filters Associative array of field => value filters
 * @param array $params Reference to parameters array
 * @param array $config Configuration options
 * @return string WHERE clause
 */
function buildWhereConditions($filters, &$params, $config = []) {
    $where_conditions = [];
    
    foreach ($filters as $field => $value) {
        if (!empty($value)) {
            if (isset($config['special_handlers'][$field])) {
                // Custom handler for specific fields
                $handler = $config['special_handlers'][$field];
                if (is_callable($handler)) {
                    $result = $handler($field, $value, $where_conditions, $params);
                    if ($result) {
                        $where_conditions[] = $result;
                    }
                }
            } else if (isset($config['like_fields']) && in_array($field, $config['like_fields'])) {
                // LIKE search fields
                $where_conditions[] = "$field LIKE ?";
                $params[] = "%$value%";
            } else if (isset($config['range_fields']) && in_array($field, $config['range_fields'])) {
                // Range fields (e.g., date_from, date_to)
                if (strpos($field, '_from') !== false) {
                    $base_field = str_replace('_from', '', $field);
                    $where_conditions[] = "$base_field >= ?";
                    $params[] = $value;
                } else if (strpos($field, '_to') !== false) {
                    $base_field = str_replace('_to', '', $field);
                    $where_conditions[] = "$base_field <= ?";
                    $params[] = $value;
                }
            } else {
                // Exact match fields
                $where_conditions[] = "$field = ?";
                $params[] = $value;
            }
        }
    }
    
    return !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
}

/**
 * Validates and sanitizes sort parameters
 * 
 * @param string $sort_by Requested sort column
 * @param string $sort_order Requested sort order
 * @param array $allowed_columns Array of allowed sort columns
 * @param string $default_sort Default sort column
 * @return array [sort_by, sort_order]
 */
function validateSortParams($sort_by, $sort_order, $allowed_columns, $default_sort = 'created_at') {
    $sort_by = in_array($sort_by, $allowed_columns) ? $sort_by : $default_sort;
    $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
    
    return [$sort_by, $sort_order];
}

/**
 * Generates sort link for table headers
 * 
 * @param string $column Database column name
 * @param string $label Display label
 * @param string $current_sort Current sort column
 * @param string $current_order Current sort order
 * @param array $preserve_params Array of parameters to preserve in URL
 * @return string HTML link
 */
function generateSortLink($column, $label, $current_sort, $current_order, $preserve_params = []) {
    $new_order = ($current_sort === $column && $current_order === 'DESC') ? 'ASC' : 'DESC';
    $sort_icon = '';
    
    if ($current_sort === $column) {
        $sort_icon = $current_order === 'ASC' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    } else {
        $sort_icon = ' <i class="fas fa-sort"></i>';
    }
    
    // Build URL with preserved parameters
    $query_params = [
        'sort' => $column,
        'order' => $new_order
    ];
    
    foreach ($preserve_params as $param) {
        if (isset($_GET[$param]) && !empty($_GET[$param])) {
            $query_params[$param] = $_GET[$param];
        }
    }
    
    $url = '?' . http_build_query($query_params);
    
    return "<a href='$url' class='text-decoration-none text-dark'>$label$sort_icon</a>";
}

/**
 * Generates pagination links
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param array $preserve_params Parameters to preserve in pagination links
 * @param int $max_links Maximum number of page links to show
 * @return string HTML pagination
 */
function generatePagination($current_page, $total_pages, $preserve_params = [], $max_links = 5) {
    if ($total_pages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous link
    $prev_disabled = $current_page <= 1 ? 'disabled' : '';
    $prev_params = array_merge(['page' => $current_page - 1], $preserve_params);
    $html .= '<li class="page-item ' . $prev_disabled . '">';
    $html .= '<a class="page-link" href="?' . http_build_query($prev_params) . '">';
    $html .= '<i class="fas fa-chevron-left"></i></a></li>';
    
    // Calculate start and end page numbers
    $half_links = floor($max_links / 2);
    $start_page = max(1, $current_page - $half_links);
    $end_page = min($total_pages, $start_page + $max_links - 1);
    
    if ($end_page - $start_page < $max_links - 1) {
        $start_page = max(1, $end_page - $max_links + 1);
    }
    
    // Page number links
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = $i == $current_page ? 'active' : '';
        $page_params = array_merge(['page' => $i], $preserve_params);
        $html .= '<li class="page-item ' . $active . '">';
        $html .= '<a class="page-link" href="?' . http_build_query($page_params) . '">' . $i . '</a></li>';
    }
    
    // Next link
    $next_disabled = $current_page >= $total_pages ? 'disabled' : '';
    $next_params = array_merge(['page' => $current_page + 1], $preserve_params);
    $html .= '<li class="page-item ' . $next_disabled . '">';
    $html .= '<a class="page-link" href="?' . http_build_query($next_params) . '">';
    $html .= '<i class="fas fa-chevron-right"></i></a></li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Builds filter form with common configurations
 * 
 * @param array $filters_config Configuration for filters
 * @param array $current_values Current filter values
 * @return string HTML filter form
 */
function buildFilterForm($filters_config, $current_values = []) {
    $html = '<form method="GET" action="" class="row g-3">';
    
    foreach ($filters_config as $filter) {
        $html .= '<div class="' . ($filter['width'] ?? 'col-md-3') . '">';
        
        if ($filter['type'] === 'search') {
            $html .= '<div class="input-group">';
            $html .= '<span class="input-group-text"><i class="fas fa-search"></i></span>';
            $html .= '<input type="text" name="' . $filter['name'] . '" class="form-control" ';
            $html .= 'placeholder="' . ($filter['placeholder'] ?? 'Search...') . '" ';
            $html .= 'value="' . htmlspecialchars($current_values[$filter['name']] ?? '') . '">';
            $html .= '</div>';
        } 
        elseif ($filter['type'] === 'select') {
            $html .= '<select name="' . $filter['name'] . '" class="form-select">';
            $html .= '<option value="">' . ($filter['placeholder'] ?? 'Select...') . '</option>';
            
            foreach ($filter['options'] as $value => $label) {
                $selected = ($current_values[$filter['name']] ?? '') == $value ? 'selected' : '';
                $html .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
            }
            
            $html .= '</select>';
        }
        elseif ($filter['type'] === 'date') {
            $html .= '<label class="form-label">' . ($filter['label'] ?? ucfirst($filter['name'])) . '</label>';
            $html .= '<input type="date" name="' . $filter['name'] . '" class="form-control" ';
            $html .= 'value="' . htmlspecialchars($current_values[$filter['name']] ?? '') . '">';
        }
        
        $html .= '</div>';
    }
    
    $html .= '<div class="col-md-2">';
    $html .= '<button type="submit" class="btn btn-primary w-100">Filter</button>';
    
    // Clear filters button if any filters are active
    $has_filters = !empty(array_filter($current_values, function($v) { return $v !== ''; }));
    if ($has_filters) {
        $html .= '<a href="' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '" class="btn btn-outline-secondary w-100 mt-2">Clear Filters</a>';
    }
    
    $html .= '</div>';
    $html .= '</form>';
    
    return $html;
}

/**
 * Fetches paginated data from database
 * 
 * @param PDO $db Database connection
 * @param string $sql_base Base SQL query (without LIMIT)
 * @param string $sql_count SQL count query
 * @param array $params Query parameters
 * @param int $page Current page
 * @param int $per_page Items per page
 * @return array [data, total_records, total_pages]
 */
function fetchPaginatedData($db, $sql_base, $sql_count, $params, $page = 1, $per_page = 10) {
    // Get total count
    $stmt = $db->prepare($sql_count);
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
    // Calculate offset
    $offset = ($page - 1) * $per_page;
    
    // Add pagination to base query
    $sql = $sql_base . " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    // Fetch data
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    return [
        'data' => $data,
        'total_records' => $total_records,
        'total_pages' => $total_pages
    ];
}