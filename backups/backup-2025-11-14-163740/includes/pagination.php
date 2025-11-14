<?php
/**
 * Pagination Helper
 */
class Pagination {
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $baseUrl;
    
    public function __construct($totalItems, $itemsPerPage = 20, $currentPage = 1, $baseUrl = '') {
        $this->totalItems = max(0, intval($totalItems));
        $this->itemsPerPage = max(1, intval($itemsPerPage));
        $this->currentPage = max(1, intval($currentPage));
        $this->baseUrl = $baseUrl;
    }
    
    public function getOffset() {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }
    
    public function getLimit() {
        return $this->itemsPerPage;
    }
    
    public function getTotalPages() {
        return ceil($this->totalItems / $this->itemsPerPage);
    }
    
    public function render($queryParams = []) {
        $totalPages = $this->getTotalPages();
        if ($totalPages <= 1) return '';
        
        $html = '<div class="pagination">';
        
        // Previous button
        if ($this->currentPage > 1) {
            $prevParams = array_merge($queryParams, ['page' => $this->currentPage - 1]);
            $html .= '<a href="?' . http_build_query($prevParams) . '" class="btn btn-sm btn-outline">&laquo; Previous</a>';
        }
        
        // Page numbers
        $html .= '<span class="pagination-info">Page ' . $this->currentPage . ' of ' . $totalPages . '</span>';
        
        // Next button
        if ($this->currentPage < $totalPages) {
            $nextParams = array_merge($queryParams, ['page' => $this->currentPage + 1]);
            $html .= '<a href="?' . http_build_query($nextParams) . '" class="btn btn-sm btn-outline">Next &raquo;</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
?>

