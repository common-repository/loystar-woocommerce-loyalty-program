<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Wc_Ls_Customer_Details extends WP_List_Table {

	/**
	 * Result count
	 * 
     * counts the number of returned array result, if -1, then there was an api error
	 * @var int
	 */
	public $result_count = 0;

    /**
     * Sorting, asc or desc
     * 
     * @var string
     */
    private $order_by = 'desc';

    public function __construct() {
        parent::__construct(array(
            'singular' => 'result',
            'plural' => 'results',
            'ajax' => false,
            'screen' => wc_loystar()->parent_slug(),//since its the same as the parent link
        ));
    }

    public function get_columns() {
        return array(
            'first_name' => __('First Name', WC_LS_TEXT_DOMAIN),
            'last_name' => __('Last Name', WC_LS_TEXT_DOMAIN),
            'email' => __('Email', WC_LS_TEXT_DOMAIN),
            'inner_id' => __('i_id',WC_LS_TEXT_DOMAIN),
            'id' => __('ID', WC_LS_TEXT_DOMAIN),
            'phone' => __('Phone Number', WC_LS_TEXT_DOMAIN),
            'gender' => __('Gender', WC_LS_TEXT_DOMAIN),
            'dob' => __('Date of Birth', WC_LS_TEXT_DOMAIN),
            'created_at' => __('Date Created', WC_LS_TEXT_DOMAIN),
            'updated_at' => __('Last Updated', WC_LS_TEXT_DOMAIN),
            'action' => __('Actions', WC_LS_TEXT_DOMAIN)
        );
    }

    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $data = $this->table_data();
        usort($data, array(&$this, 'sort_data'));
        $perPage = $this->get_items_per_page('results_per_page', 15);
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);
        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page' => $perPage
        ));
        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns() {
        return array('inner_id','id','created_at','updated_at');
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'first_name' => array('first_name', false),
            'last_name' => array('last_name', false),
            'email' => array('email', false),
            'created_at' => array('created_at', false),
        );
        return apply_filters('wc_ls_customer_programs_details_sortable_columns', $sortable_columns);
    }

    /**
     * Get the table data
     *
     * @return Array|mixed
     */
    private function table_data() {
        $results = wc_loystar()->get_cachable_data('customers',['id'=>0,'single_data_index_form'=>true]);;
        //call the looper guy :O
        return $this->looper($results);
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'inner_id':
            case 'id':
            case 'first_name':
            case 'last_name':
            case 'email':
            case 'phone':
            case 'dob':
            case 'gender':
            case 'created_at':
            case 'updated_at':
            case 'action':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data($a, $b) {
        // Set defaults
        $orderby = 'inner_id';//'name';
        $order = $this->order_by;
        // If orderby is set, use this as the sort column
        if (!empty($_GET['orderby'])) {
            $orderby = $_GET['orderby'];
        }
        // If order is set use this as the order
        if (!empty($_GET['order'])) {
            $order = $_GET['order'];
        }
        $result = strcmp($a[$orderby], $b[$orderby]);
        if ($order === 'asc') {
            return $result;
        }
        return -$result;
    }

    /**
     * The looper
     * 
     * @return array
     */
    private function looper($array){
        $data = array();
        $chosen_data = array();//to store the selected customer data
        if($array){
        $max_num = count($array);
        $j = ($this->order_by == 'desc') ? count($array)  : 1;
        foreach ($array as $val) {
            $created_at = date('d, M Y @ H:i',strtotime($val['created_at']) );
            $updated_at = date('d, M Y @ H:i',strtotime($val['updated_at']) );
            //make sure you dont show deleted
            if(!$val['deleted']){
                //change all null values to empty string, might take memory ish shaa :), naa i doubt
                $val = wc_loystar()->replace_value($val);
                //do form
                $form = '<a class="button tips ls-edit-btn" title="view all customers" href="http://web.loystar.co/d/customers" target="_blank">
                <span class="dashicons dashicons-external"></span></a>';
                $args = array(
                    'inner_id'=> $this->add_leading_zeros($max_num,$j),
                    'id' => '<span id ="'.$val['id'].'">'.$val['id'].'</span>',
                    'first_name' => $val['first_name'],
                    'last_name' => $val['last_name'],
                    'email' => $val['email'],
                    'phone' => $val['phone_number'],
                    'dob' => $val['date_of_birth'],
                    'gender' => $val['sex'],
                    'created_at' => $created_at,
                    'updated_at' => $updated_at,
                    'action' => $form
                );
                //( ($this->order_by == 'desc') ? $j-- : $j++ );
                $data[] = $args;
            }
        }
        $this->result_count = count($data);
        }
        else{
            $this->result_count = -1;//shows its an error
        }
       return $data;
    }

    /**
     * Helps in adding proper leading zeros
     * 
     * @param int $max_num
     * @param int $min_num
     * @return string
     */
    private function add_leading_zeros($max_num,$min_num){
        $max_str = (string)$max_num;
        $min_str =  (string)$min_num;
        //convert ans to string too
        $ans_str = (string)$ans;
        //now split both and compare thier lengths, that way we know how many zeros we hadding
        $max_arr = str_split($max_str);
        $min_arr = str_split($min_str);
        $zero_len = count($max_arr) - count($min_arr);
        $value = "";
        for($i=0; $i<$zero_len; $i++){
            $value .= "0";
        }
        return ($value.$min_str);
    }

}
