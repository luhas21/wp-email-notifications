<?php
/*
Plugin Name: WP Email Notifications
Description: Sends email notifications when a new post is published and allows users to manage subscription emails with confirmation and customizable post types.
Version: 1.0
Author: Petr Pedro Sahula
*/

// Create subscriber table with confirmation status
function create_subscriber_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscriber_emails';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        confirmed tinyint(1) NOT NULL DEFAULT 0,
        confirm_code varchar(255) NOT NULL,
        date_registered datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_subscriber_table');

// Register plugin settings for enabled post types
function email_notifications_settings() {
    register_setting('email_notifications_settings_group', 'enabled_post_types');
}
add_action('admin_init', 'email_notifications_settings');

// Add settings page for enabling post types
function email_notifications_settings_page() {
    add_options_page(
        'Email Notifications Settings',
        'Email Notifications',
        'manage_options',
        'email-notifications',
        'email_notifications_settings_page_html'
    );
}
add_action('admin_menu', 'email_notifications_settings_page');

function email_notifications_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $post_types = get_post_types(array('public' => true), 'objects');
    $enabled_post_types = get_option('enabled_post_types', array());

    ?>
    <div class="wrap">
        <h1>Email Notifications Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('email_notifications_settings_group'); ?>
            <?php do_settings_sections('email_notifications_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable notifications for post types:</th>
                    <td>
                        <?php foreach ($post_types as $post_type) : ?>
                            <label>
                                <input type="checkbox" name="enabled_post_types[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $enabled_post_types)); ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Send notification on publish
function send_notification_on_publish($ID, $post) {
    $enabled_post_types = get_option('enabled_post_types', array());

    if (!in_array($post->post_type, $enabled_post_types) || get_post_meta($ID, '_disable_notification', true)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'subscriber_emails';
    $emails = $wpdb->get_col("SELECT email FROM $table_name WHERE confirmed = 1");

    $subject = 'Nový příspěvek na vašem oblíbeném webu';
    $message = 'Byl publikován nový příspěvek: ' . get_permalink($ID);

    foreach ($emails as $email) {
        $unsubscribe_link = add_query_arg(array('unsubscribe' => $email), get_site_url());
        $message .= "\n\nPokud si nepřejete dostávat další upozornění, klikněte na tento odkaz: " . $unsubscribe_link;
        wp_mail($email, $subject, $message);
    }
}
add_action('publish_post', 'send_notification_on_publish', 10, 2);
add_action('publish_page', 'send_notification_on_publish', 10, 2);

function add_cpt_notifications() {
    $post_types = get_post_types(array('public' => true), 'names');
    foreach ($post_types as $post_type) {
        if ($post_type !== 'post' && $post_type !== 'page') {
            add_action("publish_{$post_type}", 'send_notification_on_publish', 10, 2);
        }
    }
}
add_action('init', 'add_cpt_notifications');

// Handle unsubscribe request
function handle_unsubscribe_request() {
    if (isset($_GET['unsubscribe'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscriber_emails';
        $email = sanitize_email($_GET['unsubscribe']);
        $wpdb->delete($table_name, array('email' => $email));
        echo 'Váš email byl úspěšně odstraněn z databáze.';
        exit;
    }
}
add_action('init', 'handle_unsubscribe_request');

// Add subscriber form
function subscriber_form() {
    if (isset($_POST['subscriber_email'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscriber_emails';
        $email = sanitize_email($_POST['subscriber_email']);
        $confirm_code = wp_generate_password(20, false);
        
        if ($wpdb->insert($table_name, array('email' => $email, 'confirm_code' => $confirm_code))) {
            $confirm_link = add_query_arg(array('confirm' => $confirm_code), get_site_url());
            $subject = 'Potvrďte svoji emailovou adresu';
            $message = 'Prosím, potvrďte svoji emailovou adresu kliknutím na následující odkaz: ' . $confirm_link;
            wp_mail($email, $subject, $message);
            echo 'Email byl úspěšně přidán. Prosím, potvrďte svoji registraci.';
        } else {
            echo 'Email se nepodařilo přidat.';
        }
    }
    ?>
    <form method="post">
        <input type="email" name="subscriber_email" required>
        <input type="submit" value="Přidat email">
    </form>
    <?php
}
add_shortcode('subscriber_form', 'subscriber_form');

// Handle email confirmation
function handle_email_confirmation() {
    if (isset($_GET['confirm'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscriber_emails';
        $confirm_code = sanitize_text_field($_GET['confirm']);
        
        $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE confirm_code = %s", $confirm_code));
        
        if ($subscriber) {
            $wpdb->update($table_name, array('confirmed' => 1), array('id' => $subscriber->id));
            echo 'Vaše emailová adresa byla úspěšně potvrzena.';
            exit;
        } else {
            echo 'Neplatný potvrzovací kód.';
            exit;
        }
    }
}
add_action('init', 'handle_email_confirmation');

// Add notification metabox
function add_notification_metabox() {
    $post_types = get_post_types(array('public' => true), 'names');
    foreach ($post_types as $post_type) {
        add_meta_box(
            'notification_metabox',
            'Nastavení notifikace',
            'notification_metabox_callback',
            $post_type,
            'side'
        );
    }
}
add_action('add_meta_boxes', 'add_notification_metabox');

function notification_metabox_callback($post) {
    $value = get_post_meta($post->ID, '_disable_notification', true);
    ?>
    <label for="disable_notification">Neodesílat upozornění na tento příspěvek</label>
    <input type="checkbox" name="disable_notification" id="disable_notification" value="yes" <?php checked($value, 'yes'); ?>>
    <?php
}

function save_notification_meta($post_id) {
    if (array_key_exists('disable_notification', $_POST)) {
        update_post_meta($post_id, '_disable_notification', 'yes');
    } else {
        delete_post_meta($post_id, '_disable_notification');
    }
}
add_action('save_post', 'save_notification_meta');

// Add admin menu for import/export and managing emails
function email_admin_menu() {
    add_menu_page(
        'Email Management',
        'Email Management',
        'manage_options',
        'email-management',
        'email_management_page',
        'dashicons-email',
        20
    );
}
add_action('admin_menu', 'email_admin_menu');

function email_management_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscriber_emails';
    $emails = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <div class="wrap">
        <h1>Email Management</h1>
        <h2>Emails</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Date Registered</th>
                    <th>Confirmed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emails as $email) : ?>
                    <tr>
                        <td><?php echo esc_html($email->email); ?></td>
                        <td><?php echo esc_html($email->date_registered); ?></td>
                        <td><?php echo $email->confirmed ? 'Ano' : 'Ne'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" enctype="multipart/form-data">
            <h2>Export Emails</h2>
            <input type="submit" name="export_emails" class="button button-primary" value="Export Emails">
        </form>
        <form method="post" enctype="multipart/form-data">
            <h2>Import Emails</h2>
            <input type="file" name="email_csv" accept=".csv">
            <input type="submit" name="import_emails" class="button button-primary" value="Import Emails">
        </form>
    </div>
    <?php
}

// Export emails
function export_emails() {
    if (isset($_POST['export_emails'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscriber_emails';
        $emails = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=emails.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Email', 'Confirmed', 'Confirm Code', 'Date Registered'));

        foreach ($emails as $email) {
            fputcsv($output, $email);
        }

        fclose($output);
        exit;
    }
}
add_action('admin_init', 'export_emails');

// Import emails
function import_emails() {
    if (isset($_POST['import_emails']) && !empty($_FILES['email_csv']['tmp_name'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'subscriber_emails';

        $file = fopen($_FILES['email_csv']['tmp_name'], 'r');

        // Try to import with header
        if (($data = fgetcsv($file)) !== false) {
            while (($row = fgetcsv($file)) !== false) {
                $email = sanitize_email($row[0]);
                if (is_email($email)) {
                    $wpdb->insert($table_name, array('email' => $email, 'confirm_code' => $row[1], 'date_registered' => $row[2], 'confirmed' => $row[1]));
                }
            }
        } else {
            // Try to import without header
            while (($email = fgets($file)) !== false) {
                $email = trim($email);
                if (is_email($email)) {
                    $wpdb->insert($table_name, array('email' => $email, 'confirm_code' => 'confirmed', 'date_registered' => date('Y-m-d'), 'confirmed' => 1));
                }
            }
        }

        fclose($file);
        echo '<div class="updated"><p>Emails were successfully imported.</p></div>';
    }
}
add_action('admin_init', 'import_emails');

?>
