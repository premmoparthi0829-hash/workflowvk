<?php
class TableManager {
    private $db;
    private $table;
    private $config;
    
    public function __construct($db, $table, $config = []) {
        $this->db = $db;
        $this->table = $table;
        $this->config = array_merge([
            'default_sort' => 'created_at',
            'default_order' => 'DESC',
            'per_page' => 10,
            'allowed_sort_columns' => ['created_at']
        ], $config);
    }
    
    public function getData($filters = [], $page = 1) {
        $params = [];
        $where_sql = $this->buildWhereSql($filters, $params);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$this->table} {$where_sql}";
        $stmt = $this->db->prepare($count_sql);
        $stmt->execute($params);
        $total_records = $stmt->fetchColumn();
        
        // Calculate pagination
        $total_pages = ceil($total_records / $this->config['per_page']);
        $offset = ($page - 1) * $this->config['per_page'];
        
        // Build main query
        $sort_by = in_array($_GET['sort'] ?? '', $this->config['allowed_sort_columns']) 
            ? $_GET['sort'] 
            : $this->config['default_sort'];
        $sort_order = strtoupper($_GET['order'] ?? '') === 'ASC' ? 'ASC' : $this->config['default_order'];
        
        $sql = "SELECT * FROM {$this->table} {$where_sql} ORDER BY {$sort_by} {$sort_order} LIMIT ? OFFSET ?";
        $params[] = $this->config['per_page'];
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'sort_by' => $sort_by,
            'sort_order' => $sort_order
        ];
    }
    
    private function buildWhereSql($filters, &$params) {
        $conditions = [];
        
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $conditions[] = "{$field} LIKE ?";
                $params[] = "%{$value}%";
            }
        }
        
        return !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    }
}