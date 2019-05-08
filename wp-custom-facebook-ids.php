<?php
/*+
 * Plugin Name: WP Custom Facebook IDs
 * Description: Table for Facebook IDs
 * Plugin URI: https://github.com/andresgmh/wp-custom-facebook-ids
 * Author URI: https://github.com/andresgmh/wp-custom-facebook-ids
 * Author: Andres Menco Haeckemrann
 * Text Domain: wp_custom_facebook_ids
 * Version: 1.1
*/


global $wp_custom_facebook_ids_db_version;
$wp_custom_facebook_ids_db_version = '1.0';

/**
* register_activation_hook implementation
*
* Called when user activates plugin first time
* Create needed database tables
*/
function wp_custom_facebook_ids_install()
{
    global $wpdb;
    global $wp_custom_facebook_ids_db_version;

    $table_name = $wpdb->prefix . 'custom_fb_admin_users'; // do not forget about tables prefix

    // sql to create the table
    //Check to see if the table exists already, if not, then create it
        if($wpdb->get_var( "show tables like '$table_name'" ) != $table_name ) 
        {
  
            $sql = "CREATE TABLE " . $table_name . " (
                id int(11) NOT NULL AUTO_INCREMENT,
                fid MEDIUMTEXT NOT NULL,
                PRIMARY KEY  (id)
            );";

            // Not execute sql directly
            // Calling dbDelta which cant migrate database
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // saving database version for later use (on upgrade)
            add_option('wp_custom_facebook_ids_db_version', $wp_custom_facebook_ids_db_version);
        }

}

register_activation_hook(__FILE__, 'wp_custom_facebook_ids_install');

/**
* WP Custom Table Facebook IDs
* ============================================================================
* http://codex.wordpress.org/Class_Reference/WP_List_Table
* http://wordpress.org/extend/plugins/custom-list-table-example/
*/

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
* WP_Custom_Facebook_Ids_Table class that will display Facebook Ids
* records a table
*/
class WP_Custom_Facebook_Ids_Table extends WP_List_Table
{
    /**
    * Constructor
    */
    function __construct()
    {
        global $status, $page;

        parent::__construct(array(
            'singular' => 'fbid',
            'plural' => 'fbids',
        ));
    }

    /**
    * Default column renderer
    *
    * @param $item - row (key, value array)
    * @param $column_name - string (key)
    * @return HTML
    */
    function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    /**
    * Render specific column
    *
    *
    * @param $item - row (key, value array)
    * @return HTML
    */
    /*function column_age($item)
    {
        return '<em>' . $item['fid'] . '</em>';
    }*/

    /**
    * Rnder column with actions
    *
    * @param $item - row (key, value array)
    * @return HTML
    */
    function column_fid($item)
    {
        /*Actions*/
        $actions = array(
            'edit' => sprintf('<a href="?page=fbids_form&id=%s">%s</a>', $item['id'], __('Edit', 'wp_custom_facebook_ids')),
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Delete', 'wp_custom_facebook_ids')),
        );

        return sprintf('%s %s',
            $item['fid'],
            $this->row_actions($actions)
        );
    }

    /**
    * Checkbox column renders
    *
    * @param $item - row (key, value array)
    * @return HTML
    */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['id']
        );
    }

    /**
    * Return columns to display in table
    *
    * @return array
    */
    function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />', //Render a checkbox instead of text
            'fid' => __('Facebook ID', 'wp_custom_facebook_ids'),
        );
        return $columns;
    }

    /**
    * Return columns that may be used to sort table.
    * True on name column means that its default sort. False = not sort
    *
    * @return array
    */
    function get_sortable_columns()
    {
        $sortable_columns = array(
            'fid' => array('fid', true),
        );
        return $sortable_columns;
    }

    /**
    * Return array of bult actions if has any
    *
    * @return array
    */
    function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    /**
    * Processes bulk actions
    */
    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_fb_admin_users'; // do not forget about tables prefix

        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (is_array($ids)) $ids = implode(',', $ids);

            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
            }
        }
    }

    /**
        * Get rows from database and prepare them to be showed in table
        */
    function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_fb_admin_users'; 

        $per_page = 5; // Records will be shown per page

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        //Table headers
        $this->_column_headers = array($columns, $hidden, $sortable);

        // Process bulk action
        $this->process_bulk_action();

        // Pagination settings
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

        // Query params
        $paged = isset($_REQUEST['paged']) ? ($per_page * max(0, intval($_REQUEST['paged']) - 1)) : 0;
        $orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'fid';
        $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';

        // Define $items array
        $this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);

        // Cnfigure pagination
        $this->set_pagination_args(array(
            'total_items' => $total_items, // total items
            'per_page' => $per_page, // per page constant
            'total_pages' => ceil($total_items / $per_page) // calculate pages count
        ));
    }
}

/**
    * Admin page
    * ============================================================================
    *
    * http://codex.wordpress.org/Administration_Menus
    */

/**
* admin_menu hook implementation
**/
function wp_custom_facebook_ids_admin_menu()
{
    add_menu_page(__('Facebook IDs', 'wp_custom_facebook_ids'), __('Facebook IDs', 'wp_custom_facebook_ids'), 'activate_plugins', 'fbids', 'wp_custom_facebook_ids_page_handler');
    add_submenu_page('fbids', __('Facebook IDs', 'wp_custom_facebook_ids'), __('Facebook IDs', 'wp_custom_facebook_ids'), 'activate_plugins', 'fbids', 'wp_custom_facebook_ids_page_handler');
    // add new will be described in next part
    add_submenu_page('fbids', __('Add new', 'wp_custom_facebook_ids'), __('Add new', 'wp_custom_facebook_ids'), 'activate_plugins', 'fbids_form', 'wp_custom_facebook_ids_form_page_handler');
}

add_action('admin_menu', 'wp_custom_facebook_ids_admin_menu');

/**
* Renders WP Facebook IDs table
**/
function wp_custom_facebook_ids_page_handler()
{
    global $wpdb;

    $table = new WP_Custom_Facebook_Ids_Table();
    $table->prepare_items();

    $message = '';
    if ('delete' === $table->current_action()) {
        $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items deleted: %d', 'wp_custom_facebook_ids'), count($_REQUEST['id'])) . '</p></div>';
    }
    ?>
<div class="wrap">

    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Facebook IDs', 'wp_custom_facebook_ids')?> <a class="add-new-h2"
                                    href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=fbids_form');?>"><?php _e('Add new', 'wp_custom_facebook_ids')?></a>
    </h2>
    <?php echo $message; ?>

    <form id="facebook-ids-table" method="GET">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
        <?php $table->display() ?>
    </form>

</div>
<?php
}

/**
    * Form for adding andor editing row
    * ============================================================================
**/
function wp_custom_facebook_ids_form_page_handler()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_fb_admin_users';

    $message = '';
    $notice = '';


    $default = array(
        'id' => 0,
        'fid' => '',
    );

    // Verifying does this request is post back and have correct nonce
    if (wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
        // combine  default item with request params
        $item = shortcode_atts($default, $_REQUEST);
        // validate data, and if all ok save item to database
        // if id is zero insert otherwise update
        $item_valid = wp_custom_facebook_ids_validate($item);
        if ($item_valid === true) {
            if ($item['id'] == 0) {
                $result = $wpdb->insert($table_name, $item);
                $item['id'] = $wpdb->insert_id;
                if ($result) {
                    $message = __('Item was successfully saved', 'wp_custom_facebook_ids');
                } else {
                    $notice = __('There was an error while saving item', 'wp_custom_facebook_ids');
                }
            } else {
                $result = $wpdb->update($table_name, $item, array('id' => $item['id']));
                if ($result) {
                    $message = __('Item was successfully updated', 'wp_custom_facebook_ids');
                } else {
                    $notice = __('There was an error while updating item', 'wp_custom_facebook_ids');
                }
            }
        } else {
            // if $item_valid not true it contains error message(s)
            $notice = $item_valid;
        }
    }
    else {
        // if this is not post back we load item to edit or give new one to create
        $item = $default;
        if (isset($_REQUEST['id'])) {
            $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $_REQUEST['id']), ARRAY_A);
            if (!$item) {
                $item = $default;
                $notice = __('Item not found', 'wp_custom_facebook_ids');
            }
        }
    }

    // here we adding our custom meta box
    add_meta_box('fbids_form_meta_box', 'Facebook ID info', 'wp_custom_facebook_ids_form_meta_box_handler', 'facebook-id', 'normal', 'default');

    ?>
<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Facebook ID', 'wp_custom_facebook_ids')?> <a class="add-new-h2"
                                href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=fbids');?>"><?php _e('back to list', 'wp_custom_facebook_ids')?></a>
    </h2>

    <?php if (!empty($notice)): ?>
    <div id="notice" class="error"><p><?php echo $notice ?></p></div>
    <?php endif;?>
    <?php if (!empty($message)): ?>
    <div id="message" class="updated"><p><?php echo $message ?></p></div>
    <?php endif;?>

    <form id="form" method="POST">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
        <?php /* NOTICE: here we storing id to determine will be item added or updated */ ?>
        <input type="hidden" name="id" value="<?php echo $item['id'] ?>"/>

        <div class="metabox-holder" id="poststuff">
            <div id="post-body">
                <div id="post-body-content">
                    <?php /*Custom meta box */ ?>
                    <?php do_meta_boxes('facebook-id', 'normal', $item); ?>
                    <input type="submit" value="<?php _e('Save', 'wp_custom_facebook_ids')?>" id="submit" class="button-primary" name="submit">
                </div>
            </div>
        </div>
    </form>
</div>
<?php
}

/**
    * Rnders our custom meta box
    * $item is row
    *
    * @param $item
    */
function wp_custom_facebook_ids_form_meta_box_handler($item)
{
    ?>

<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
    <tbody>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="fid"><?php _e('ID', 'wp_custom_facebook_ids')?></label>
        </th>
        <td>
            <input id="fid" name="fid" type="text" style="width: 95%" value="<?php echo esc_attr($item['fid'])?>"
                    size="50" class="code" placeholder="<?php _e('Facebook ID', 'wp_custom_facebook_ids')?>" required>
        </td>
    </tr>
    </tbody>
</table>
<?php
}

/**
    * Validates data and retrieve bool on success
    * and error message(s) on error
    *
    * @param $item
    * @return bool|string
*/
function wp_custom_facebook_ids_validate($item)
{
    $messages = array();

    if (empty($item['fid'])) $messages[] = __('Facebook ID is required', 'wp_custom_facebook_ids');

    if (empty($messages)) return true;
    return implode('<br />', $messages);
}

