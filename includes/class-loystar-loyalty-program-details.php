<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Wc_Ls_Loyalty_Details extends WP_List_Table {

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
            'name' => __('Name', WC_LS_TEXT_DOMAIN),
            'inner_id' => __('i_id',WC_LS_TEXT_DOMAIN),
            'id' => __('ID', WC_LS_TEXT_DOMAIN),
            'threshold' => __('Spending target', WC_LS_TEXT_DOMAIN),
            'reward' => __('Reward', WC_LS_TEXT_DOMAIN),
            'program_type' => __('Program Type', WC_LS_TEXT_DOMAIN),
            'cost_of_stamp' => __('Cost of Stamp', WC_LS_TEXT_DOMAIN),
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
        return array('inner_id','id','created_at','updated_at','cost_of_stamp');
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'name' => array('name', false),
            'program_type' => array('program_type', false),
            'created_at' => array('created_at', false),
        );
        return apply_filters('wc_ls_loyalty_programs_details_sortable_columns', $sortable_columns);
    }

    /**
     * Get the table data
     *
     * @return Array|mixed
     */
    private function table_data() {
        $results = wc_loystar()->get_cachable_data('loyalty',['id'=>0,'single_data_index_form'=>true]);;
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
            case 'name':
            case 'inner_id':
            case 'id':
            case 'threshold':
            case 'reward':
            case 'program_type':
            case 'cost_of_stamp':
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
        $chosen_data = array();//to store the selected loyalty data
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
                $form = '';
                $few_attr = '';
                if(wc_loystar()->is_merchant_subscription_expired()){
                    $few_attr = ' disabled title="Renew your loystar subscription to access this feature" ';
                }
                $form = '
                <a href="'.add_query_arg(['wc_ls_updater'=>$val['id'],'ls-update-program'=>wp_create_nonce('ls-update-program')],admin_url(wc_loystar()->parent_slug(true) ) ).'" class="ls-cool-btn wc-ls-action-btn" '.$few_attr.'>
                <span class="dashicons dashicons-yes"></span> Activate Loyalty</a>
                &nbsp;&nbsp;&nbsp;<a class="wc-ls-action-btn button tips ls-edit-btn" '.$few_attr.' href="'.add_query_arg(['loyalty_id'=>$val['id']],admin_url(wc_loystar()->parent_slug(true).'-loyalty' ) ).'">
                <span class="dashicons dashicons-edit"></span></a>
               ';
                $args = array(
                    'inner_id'=> $this->add_leading_zeros($max_num,$j),
                    'id' => '<span id ="'.$val['id'].'">'.$val['id'].'</span>',
                    'name' => $val['name'],
                    'threshold' => $val['threshold'],
                    'reward' => $val['reward'],
                    'program_type' => wc_loystar()->program_type_display($val['program_type']),
                    'cost_of_stamp' => $val['cost_of_stamp'],
                    'created_at' => $created_at,
                    'updated_at' => $updated_at,
                    'action' => $form
                );
                ( ($this->order_by == 'desc') ? $j-- : $j++ );
                if($val['id'] == wc_loystar()->loyalty_program()){//its the chosen one, store separately
                    $chosen_data[] = $args;
                    $chosen_data[0]['inner_id'] = ($this->order_by == 'desc') ? $this->add_leading_zeros($max_num,$max_num + 1): 0;
                    //add a disabled attr in the form, nice hack, :) what i can do just to make lines shorter,worth it
                    $chosen_data[0]['action'] = str_replace('class="ls-cool-btn"','class="ls-cool-btn wc-ls-action-btn" disabled',$chosen_data[0]['action']);
                    $chosen_data[0]['action'] = str_replace('Activate Loyalty','Activated Loyalty',$chosen_data[0]['action']);
                    continue;
                }
                $data[] = $args;
            }
        }

        if(empty($chosen_data)){
            //no chosen program, maybe a different merchant logged in, to avoid that, empty the old chosen loyalty program
            global $wc_ls_option_meta;
            delete_option($wc_ls_option_meta['loyalty_program']);
        }
        //now merge, doesnt really matter how its merged, cause wordpress will sort based on column value, which is 'inner_id' in our case :).
       $data = array_merge($data,$chosen_data);
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
