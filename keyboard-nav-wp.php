<?php

/*
Plugin Name: Keyboard Nav WP
Author: Lukman Nakib
Author URI: https://nkb-bd.github.io/
Description: WP Keyboard Navigation
Version: 1.0
Text Domain: aknp_keyboard_nav
Domain Path:  /language
*/

define('AKNP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AKNP_KEYBOARD_NAV_VER', '1.0');


class AdminNavPluginBoot
{
    
    const version = AKNP_KEYBOARD_NAV_VER;
    protected static $instance = null;
    public static $optionKey = "aknp_is_active";
    private $pluginName;
    
    public static function boot()
    {
        if (!static::$instance) {
            static::$instance = new self();
        }
    }
    
    public function __construct()
    {
        add_action('admin_init', function () {
            $this->setGlobals();
            $this->register();
        });
        add_action('admin_footer', [$this, 'insertSearchModalHtml']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScript']);
        add_action('wp_print_scripts', [$this, 'forcePrintScript'], 99);
    }
    
    private function register()
    {
        add_settings_field(
            "aknp-settings-status",
            __('Turn on/off Admin Navigation', 'aknp_keyboard_nav'),
            array($this, 'settingsCallback'),
            'general',
            'default',
            array(
                'label_for'    => self::$optionKey,
                'type'         => 'checkbox',
                'option_group' => "aknp_settings_options",
                'name'         => self::$optionKey,
                'description'  => __('Activate Admin Keyboard Menu Nav', 'aknp_keyboard_nav'),
                'tip'          => __('Helps to navigate menu using keyboard', 'aknp_keyboard_nav')
            )
        );
        $this->handleOption();
        
        $active = get_option(self::$optionKey) == 'on';
        if (!$active) {
            return;
        }
        $this->fireActions();
        load_plugin_textdomain( 'aknp_keyboard_nav', false, dirname( plugin_basename( __FILE__ ) ) . '/language' );
    }
    
    public function settingsCallback($args)
    {
        $checked = '';
        
        $value = get_option(self::$optionKey) == 'on';
        if ($value) {
            $checked = ' checked="checked" ';
        }
        echo '<input id="' . esc_attr($args['name']) . '"name="' . esc_attr($args['name']) . '"type="checkbox" ' . $checked . '/>';
        echo "<span class=\"wndspan\">" . esc_html($args['description']) . "</span>";
        
    }
    
    private function setGlobals()
    {
        $this->pluginName = sanitize_title(plugin_basename(__FILE__));
    }
    
    public function handleOption()
    {
        if (isset($_POST[self::$optionKey]) && !empty($_POST[self::$optionKey])) {
            $value = sanitize_key($_POST[self::$optionKey]);
            if($value ==='on'){
                update_option(self::$optionKey, $value, false);
            }
        } else {
            if (isset($_POST['option_page']) && $_POST['action'] == 'update') {
                update_option(self::$optionKey, 'no', false);
            }
        }
    }
    
    public function handleAdminBarMenu()
    {
        if (!is_admin_bar_showing()) {
            return;
        }
        global $wp_admin_bar;
        
        $admin_notice = [
            'parent' => 'top-secondary',
            'id'     => "_aknp_menu_bar_id",
            'title'  => sprintf('<span style="min-width: 100px;display: flex;" class="aknp_menubar">%s <div style="height: 20px;width: 20px;padding: 5px;">%s</div></span>',
                "Ctrl + K ", $this->searchIcon()),
        ];
        $wp_admin_bar->add_menu($admin_notice);
    }
    
    public function enqueueScript()
    {
        $menus = $this->getMenus();
        $formattedMenus = $this->getFormattedMenus($menus);
        
        wp_enqueue_style('aknp_main_style', AKNP_PLUGIN_URL . '/assets/aknp_menubar.css', [], self::version, 'all');
        wp_register_script('aknp_menubar_script', AKNP_PLUGIN_URL . 'assets/aknp_menubar.js', ['jquery-ui-autocomplete', 'jquery'], self::version, true);
        wp_enqueue_script('aknp_menubar_script');
        $adminVars = apply_filters('aknp_admin_vars', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'plugin_name' => $this->pluginName,
            'nav_id'      => "wp-admin-bar-" . "_aknp_menu_bar_id",
            'nonce'       => wp_create_nonce('aknp-ajax-nonce'),
            'menus'       => $formattedMenus,
        ]);
        wp_localize_script('aknp_menubar_script', 'aknp_admin_vars', $adminVars);
    
        // Enqueue jQuery UI and autocomplete
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-autocomplete');
    }
    
    public function fireActions()
    {
        add_action('admin_bar_menu', [$this, 'handleAdminBarMenu']);
    }
    
    public function insertSearchModalHtml()
    {
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            ob_start();
            ?>
            
            <div class="aknp_modal" id="aknp_modal-elm">
                <div class="aknp_modal-overlay aknp_modal-toggle"></div>
                <div class="aknp_modal-wrapper aknp_modal-transition">
                    <div class="aknp_modal-header">
                        <button class="aknp_modal-close aknp_modal-toggle">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                        <h2 class="aknp_modal-heading"> <?php _e('Admin Menus','aknp_keyboard_nav');?></h2>
                    </div>

                    <div class="aknp_modal-body">
                        <div class="aknp_modal-content">
                            <input autofocus type="text" id="aknp_search_box" placeholder="Search">
                            <span><?php _e('Press Enter To Go / Esc To Close','aknp_keyboard_nav'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
            echo ob_get_clean();
        }
    }
    
    private function getMenus()
    {
        global $submenu, $menu;
        $menus = [];
        if (current_user_can('manage_options')) { // ONLY DO THIS FOR ADMIN
            foreach ($menu as $item) {
                $submenu_items = false;
                if (!empty($submenu[$item[2]])) {
                    $submenu_items = $submenu[$item[2]];
                }
                $parent = wptexturize($item[0]);
                if (!is_array($submenu_items)) {
                    continue;
                }
                $submenu_items = array_values($submenu_items);  // R
                foreach ($submenu_items as $sub_key => $sub_item) {
                    if (!current_user_can($sub_item[1])) {
                        continue;
                    }
                    $title = wptexturize($sub_item[0]);
                    $url = $sub_item['2'];
                    if (false === strpos($url, '.php')) {
                        $url = menu_page_url($sub_item['2'], false);
                    } else {
                        $url = admin_url() . $url;
                    }
                    $menus[] = [
                        'parent' => $parent,
                        'title'  => $title,
                        'url'    => $url,
                        'icon'   => isset($item[6]) ? $item[6] : ''
                    ];
                }
            }
        }
        return $menus;
    }
    
    
    public function forcePrintScript()
    {
        if (is_admin()) {
            global $wp_scripts;
            if (!$wp_scripts) {
                return;
            }
            
            foreach ($wp_scripts->queue as $script) {
                if (!isset($wp_scripts->registered[$script])) {
                    continue;
                }
                
                $src = $wp_scripts->registered[$script]->src;
                if (strpos($src, plugins_url()) !== false && !strpos($src, 'aknp') !== false) {
                    wp_enqueue_script($wp_scripts->registered[$script]->handle);
                }
            }
        }
    }
    
    private function searchIcon()
    {
        return '<svg fill="#ffffff" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 30 30" width="20px" height="20px"><path d="M 13 3 C 7.4889971 3 3 7.4889971 3 13 C 3 18.511003 7.4889971 23 13 23 C 15.396508 23 17.597385 22.148986 19.322266 20.736328 L 25.292969 26.707031 A 1.0001 1.0001 0 1 0 26.707031 25.292969 L 20.736328 19.322266 C 22.148986 17.597385 23 15.396508 23 13 C 23 7.4889971 18.511003 3 13 3 z M 13 5 C 17.430123 5 21 8.5698774 21 13 C 21 17.430123 17.430123 21 13 21 C 8.5698774 21 5 17.430123 5 13 C 5 8.5698774 8.5698774 5 13 5 z"/></svg>';
    }
    
    private function getFormattedMenus($menus)
    {
        $formattedMenus = [];
        foreach ($menus as $menu) {
            $formattedMenus[] = [
                'label' => "<a href=\"{$menu['url']}\"><span class=\"wp-menu-image dashicons-before {$menu['icon']}\" aria-hidden=\"true\"></span><span class=\"wp-menu-name\">{$menu['parent']} - {$menu['title']}</span> </a>",
                'value' => $menu['parent'] . ' - ' . strip_tags($menu['title']),
            ];
        }
        return apply_filters('aknp_admin_menu_list',$formattedMenus);
    }
    
    public static function onActivation()
    {
        update_option(AdminNavPluginBoot::$optionKey, 'on', false);
    }
}

add_action('init', function () {
    AdminNavPluginBoot::boot();
});

register_activation_hook( __FILE__, function (){
    AdminNavPluginBoot::onActivation();
});

