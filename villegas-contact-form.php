<?php
/**
 * Plugin Name: Villegas Contact Form
 * Description: A custom contact form plugin for Villegas.
 * Version: 1.0.0
 * Author: Antigravity
 * Text Domain: villegas-contact-form
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register the menu item.
 */
function vcf_add_admin_menu()
{
    add_menu_page(
        'Villegas Contact Form',
        'Villegas Contact Form',
        'manage_options',
        'villegas-contact-form',
        'vcf_options_page_html',
        'dashicons-email',
        60
    );
}
add_action('admin_menu', 'vcf_add_admin_menu');

/**
 * Register settings.
 */
function vcf_settings_init()
{
    register_setting('vcf_plugin_options', 'vcf_thank_you_page_id');
    register_setting('vcf_plugin_options', 'vcf_email_publicidad_programa');
    register_setting('vcf_plugin_options', 'vcf_email_consulta_compra');
    register_setting('vcf_plugin_options', 'vcf_email_reclamo');
    register_setting('vcf_plugin_options', 'vcf_email_informacion_noticiosa');
    register_setting('vcf_plugin_options', 'vcf_recaptcha_site_key');
    register_setting('vcf_plugin_options', 'vcf_recaptcha_secret_key');

    add_settings_section(
        'vcf_plugin_section',
        __('General Settings', 'villegas-contact-form'),
        'vcf_section_callback',
        'villegas-contact-form'
    );

    add_settings_field(
        'vcf_thank_you_page_id',
        __('Thank You Page', 'villegas-contact-form'),
        'vcf_thank_you_page_render',
        'villegas-contact-form',
        'vcf_plugin_section'
    );

    add_settings_section(
        'vcf_email_section',
        __('Email Routing Settings', 'villegas-contact-form'),
        'vcf_email_section_callback',
        'villegas-contact-form'
    );

    $reasons = array(
        'vcf_email_publicidad_programa' => 'Publicidad Programa',
        'vcf_email_consulta_compra' => 'Consulta sobre compra',
        'vcf_email_reclamo' => 'Reclamo',
        'vcf_email_informacion_noticiosa' => 'Información Noticiosa',
    );

    foreach ($reasons as $id => $label) {
        add_settings_field(
            $id,
            sprintf(__('Email for %s', 'villegas-contact-form'), $label),
            'vcf_email_field_render',
            'villegas-contact-form',
            'vcf_email_section',
            array('option_name' => $id)
        );
    }

    add_settings_section(
        'vcf_recaptcha_section',
        __('reCAPTCHA v3 Settings', 'villegas-contact-form'),
        'vcf_recaptcha_section_callback',
        'villegas-contact-form'
    );

    add_settings_field(
        'vcf_recaptcha_site_key',
        __('Site Key', 'villegas-contact-form'),
        'vcf_text_field_render',
        'villegas-contact-form',
        'vcf_recaptcha_section',
        array('option_name' => 'vcf_recaptcha_site_key')
    );

    add_settings_field(
        'vcf_recaptcha_secret_key',
        __('Secret Key', 'villegas-contact-form'),
        'vcf_text_field_render',
        'villegas-contact-form',
        'vcf_recaptcha_section',
        array('option_name' => 'vcf_recaptcha_secret_key')
    );
}
add_action('admin_init', 'vcf_settings_init');

/**
 * Section callback.
 */
function vcf_section_callback()
{
    echo __('Configure the general settings below.', 'villegas-contact-form');
}

/**
 * Email Section callback.
 */
function vcf_email_section_callback()
{
    echo __('Configure the recipient emails for each contact reason. If left empty, the admin email will be used.', 'villegas-contact-form');
}

/**
 * Helper function to get reCAPTCHA keys.
 * Checks plugin settings first, then VillegasLMS, then falls back to Wordfence settings.
 *
 * @return array Array with 'site_key' and 'secret_key'.
 */
function vcf_get_recaptcha_keys()
{
    $keys = array(
        'site_key' => get_option('vcf_recaptcha_site_key'),
        'secret_key' => get_option('vcf_recaptcha_secret_key'),
    );

    // 1. Check VillegasLMS
    if (empty($keys['site_key']) || empty($keys['secret_key'])) {
        $vcp_site_key = get_option('vcp_recaptcha_site_key');
        $vcp_secret = get_option('vcp_recaptcha_secret_key');

        if (!empty($vcp_site_key) && empty($keys['site_key'])) {
            $keys['site_key'] = $vcp_site_key;
        }
        if (!empty($vcp_secret) && empty($keys['secret_key'])) {
            $keys['secret_key'] = $vcp_secret;
        }
    }

    // 2. If still empty, try to get from Wordfence
    if (empty($keys['site_key']) || empty($keys['secret_key'])) {
        global $wpdb;
        // Wordfence stores settings in a custom table, usually wp_wfls_settings
        // We need to find the table name dynamically if possible, or assume default prefix
        $table_name = $wpdb->base_prefix . 'wfls_settings';

        // Check if table exists to avoid errors
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $wf_site_key = $wpdb->get_var("SELECT value FROM $table_name WHERE name = 'recaptcha-site-key'");
            $wf_secret = $wpdb->get_var("SELECT value FROM $table_name WHERE name = 'recaptcha-secret'");

            if (!empty($wf_site_key) && empty($keys['site_key'])) {
                $keys['site_key'] = $wf_site_key;
            }
            if (!empty($wf_secret) && empty($keys['secret_key'])) {
                $keys['secret_key'] = $wf_secret;
            }
        }
    }

    return $keys;
}

/**
 * reCAPTCHA Section callback.
 */
function vcf_recaptcha_section_callback()
{
    echo '<p>Enter your Google reCAPTCHA v3 keys here to enable spam protection.</p>';

    $keys = vcf_get_recaptcha_keys();
    $plugin_site_key = get_option('vcf_recaptcha_site_key');

    if (empty($plugin_site_key) && !empty($keys['site_key'])) {
        echo '<div class="notice notice-info inline"><p><strong>Note:</strong> We detected reCAPTCHA keys from another plugin (VillegasLMS or Wordfence). You can leave these fields empty to use those keys.</p></div>';
    }
}

/**
 * Render Thank You Page field.
 */
function vcf_thank_you_page_render()
{
    $options = get_option('vcf_thank_you_page_id');
    $pages = get_pages();
    ?>
    <select name='vcf_thank_you_page_id'>
        <option value=""><?php _e('Select a page', 'villegas-contact-form'); ?></option>
        <?php foreach ($pages as $page): ?>
            <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($options, $page->ID); ?>>
                <?php echo esc_html($page->post_title); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

/**
 * Render Email fields.
 */
function vcf_email_field_render($args)
{
    $option_name = $args['option_name'];
    $options = get_option($option_name);
    ?>
    <input type='email' name='<?php echo esc_attr($option_name); ?>' value='<?php echo esc_attr($options); ?>'
        class='regular-text' placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
    <?php
}

/**
 * Render Text fields.
 */
function vcf_text_field_render($args)
{
    $option_name = $args['option_name'];
    $options = get_option($option_name);
    ?>
    <input type='text' name='<?php echo esc_attr($option_name); ?>' value='<?php echo esc_attr($options); ?>'
        class='regular-text'>
    <?php
}

/**
 * Register the shortcode.
 */
function vcf_contact_form_shortcode()
{
    // Enqueue Google Fonts
    wp_enqueue_style('google-fonts-fraunces', 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,100..900;1,9..144,100..900&display=swap', array(), null);
    wp_enqueue_style('google-fonts-lexend', 'https://fonts.googleapis.com/css2?family=Lexend:wght@100..900&display=swap', array(), null);

    // Enqueue CSS
    wp_enqueue_style('vcf-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');

    // Enqueue JS
    wp_enqueue_script('vcf-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', array(), null, true);

    // Get reCAPTCHA keys
    $recaptcha_keys = vcf_get_recaptcha_keys();
    $recaptcha_site_key = $recaptcha_keys['site_key'];

    if (!empty($recaptcha_site_key)) {
        wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key), array(), null, true);
    }

    wp_localize_script(
        'vcf-script',
        'vcf_ajax_obj',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vcf_contact_form_nonce'),
            'recaptcha_site_key' => $recaptcha_site_key,
        )
    );

    ob_start();
    ?>
    <div class="vcf-shortcode-wrapper">
        <div class="vcf-form-wrapper">

            <h1 class="font-fraunces vcf-h1-style">Contáctanos</h1>
            <p class="font-fraunces vcf-p-intro-style">Envíanos tu consulta. Nos pondremos en contacto contigo a la
                brevedad.</p>

            <div id="vcf-feedbackMessage" style="display:none;"></div>

            <form id="vcf-contactForm">
                <!-- Honeypot Field -->
                <div style="display:none !important; visibility:hidden; opacity:0; height:0; width:0; overflow:hidden;">
                    <label for="vcf_honeypot">Leave this field empty</label>
                    <input type="text" id="vcf_honeypot" name="vcf_honeypot" tabindex="-1" autocomplete="off">
                </div>

                <!-- Grid para Nombre, Email y Teléfono -->
                <div class="vcf-form-grid">
                    <!-- Nombre -->
                    <div>
                        <label for="name" class="font-lexend">Nombre</label>
                        <input type="text" id="name" name="name" required class="font-lexend vcf-input-field"
                            placeholder="Tu nombre">
                    </div>

                    <!-- Correo Electrónico -->
                    <div>
                        <label for="email" class="font-lexend">Correo Electrónico</label>
                        <input type="email" id="email" name="email" required class="font-lexend vcf-input-field"
                            placeholder="tu@email.com">
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label for="phone" class="font-lexend">Teléfono</label>
                        <input type="tel" id="phone" name="phone" class="font-lexend vcf-input-field"
                            placeholder="+56 9 1234 5678">
                    </div>
                </div>

                <!-- Motivo de Contacto -->
                <div class="form-group">
                    <label for="reason" class="font-lexend">Motivo de Contacto</label>
                    <select id="reason" name="reason" required class="font-lexend vcf-input-field">
                        <option value="" disabled selected>Selecciona un motivo</option>
                        <option value="Publicidad Programa">Publicidad Programa</option>
                        <option value="Consulta sobre compra">Consulta sobre compra</option>
                        <option value="Reclamo">Reclamo</option>
                        <option value="Información Noticiosa">Información Noticiosa</option>
                    </select>
                </div>

                <!-- Mensaje -->
                <div class="form-group">
                    <label for="message" class="font-lexend">Mensaje</label>
                    <textarea id="message" name="message" rows="5" required class="font-lexend vcf-input-field"
                        placeholder="Escribe tu mensaje aquí..."></textarea>
                </div>

                <!-- Botón -->
                <button type="submit" id="vcf-submitButton" class="font-lexend">Enviar Mensaje</button>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('villegas-contact-form', 'vcf_contact_form_shortcode');

/**
 * Handle AJAX form submission.
 */
function vcf_send_email()
{
    check_ajax_referer('vcf_contact_form_nonce', 'nonce');

    // 1. Honeypot Check
    if (!empty($_POST['vcf_honeypot'])) {
        // It's a bot. Return success to fool them, but don't send email.
        wp_send_json_success(array('message' => 'Sent'));
        exit;
    }

    // 2. reCAPTCHA Check
    $recaptcha_keys = vcf_get_recaptcha_keys();
    $recaptcha_secret = $recaptcha_keys['secret_key'];

    if (!empty($recaptcha_secret) && !empty($_POST['recaptcha_token'])) {
        $token = sanitize_text_field($_POST['recaptcha_token']);
        $response = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            array(
                'body' => array(
                    'secret' => $recaptcha_secret,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR'],
                ),
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Error verifying reCAPTCHA'));
            exit;
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if (!$result['success'] || $result['score'] < 0.5) {
            // Likely a bot
            wp_send_json_error(array('message' => 'Spam detected. Please try again.'));
            exit;
        }
    }

    // Sanitize fields
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $reason = sanitize_text_field($_POST['reason']);
    $message = sanitize_textarea_field($_POST['message']);

    // Determine recipient based on reason
    $to = get_option('admin_email'); // Fallback

    $reason_map = array(
        'Publicidad Programa' => 'vcf_email_publicidad_programa',
        'Consulta sobre compra' => 'vcf_email_consulta_compra',
        'Reclamo' => 'vcf_email_reclamo',
        'Información Noticiosa' => 'vcf_email_informacion_noticiosa',
    );

    if (isset($reason_map[$reason])) {
        $specific_email = get_option($reason_map[$reason]);
        if (!empty($specific_email) && is_email($specific_email)) {
            $to = $specific_email;
        }
    }

    $subject = "Nuevo mensaje de contacto: $reason";
    $body = "Nombre: $name\n";
    $body .= "Email: $email\n";
    $body .= "Teléfono: $phone\n";
    $body .= "Motivo: $reason\n\n";
    $body .= "Mensaje:\n$message\n";

    $headers = array('Content-Type: text/plain; charset=UTF-8', "Reply-To: $name <$email>");

    $sent = wp_mail($to, $subject, $body, $headers);

    if ($sent) {
        $thank_you_page_id = get_option('vcf_thank_you_page_id');
        $redirect_url = '';
        if (!empty($thank_you_page_id)) {
            $redirect_url = get_permalink($thank_you_page_id);
        }
        wp_send_json_success(array('redirect_url' => $redirect_url));
    } else {
        wp_send_json_error(array('message' => 'Error al enviar el correo.'));
    }
}
add_action('wp_ajax_vcf_send_email', 'vcf_send_email');
add_action('wp_ajax_nopriv_vcf_send_email', 'vcf_send_email');

/**
 * Render the options page.
 */
function vcf_options_page_html()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('vcf_plugin_options');
            do_settings_sections('villegas-contact-form');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
