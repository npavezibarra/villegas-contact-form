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
 * Register the shortcode.
 */
function vcf_contact_form_shortcode()
{
    // Enqueue styles and scripts
    wp_enqueue_style('vcf-google-fonts', 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Lexend:wght@300;400;500&display=swap', array(), null);
    wp_enqueue_style('vcf-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.0');
    wp_enqueue_script('vcf-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', array(), '1.0.0', true);

    wp_localize_script(
        'vcf-script',
        'vcf_ajax_obj',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vcf_contact_form_nonce'),
        )
    );

    ob_start();
    ?>
    <div class="vcf-shortcode-wrapper">
        <div class="vcf-form-wrapper">

            <h1 class="font-fraunces vcf-h1-style">Contáctanos</h1>
            <p class="font-fraunces vcf-p-intro-style">Envíanos tu consulta. Nos pondremos en contacto contigo a la
                brevedad.</p>

            <form id="vcf-contactForm">
                <!-- Grid para Nombre, Email y Teléfono (3 columnas en escritorio) -->
                <div class="vcf-form-grid">
                    <!-- Nombre -->
                    <div>
                        <label for="name" class="font-lexend">Nombre</label>
                        <input type="text" id="name" name="name" required class="font-lexend vcf-input-field">
                    </div>

                    <!-- Correo Electrónico -->
                    <div>
                        <label for="email" class="font-lexend">Correo Electrónico</label>
                        <input type="email" id="email" name="email" required class="font-lexend vcf-input-field">
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label for="phone" class="font-lexend">Teléfono</label>
                        <input type="tel" id="phone" name="phone" class="font-lexend vcf-input-field"
                            placeholder="(Opcional)">
                    </div>
                </div>

                <!-- Motivo de Contacto (Dropdown) -->
                <div style="margin-bottom: 24px;">
                    <label for="reason" class="font-lexend">Motivo de Contacto</label>
                    <select id="reason" name="reason" required class="font-lexend vcf-input-field">
                        <option value="" disabled selected>Seleccione un motivo</option>
                        <option value="publicidad_programa">Publicidad Programa</option>
                        <option value="consulta_compra">Consulta sobre compra</option>
                        <option value="reclamo">Reclamo</option>
                        <option value="informacion_noticiosa">Información Noticiosa</option>
                    </select>
                </div>

                <!-- Mensaje -->
                <div style="margin-bottom: 40px;">
                    <label for="message" class="font-lexend">Mensaje</label>
                    <textarea id="message" name="message" rows="5" required class="font-lexend vcf-input-field"></textarea>
                </div>

                <!-- Botón de Envío -->
                <div>
                    <button type="submit" id="vcf-submitButton" class="font-lexend">
                        Enviar Mensaje
                    </button>
                </div>
            </form>

            <!-- Mensaje de éxito/Feedback -->
            <div id="vcf-feedbackMessage" style="display: none;" class="font-lexend">
                <div class="flex-container">
                    <!-- Icono SVG de Checkmark (estilo B&N simple) -->
                    <svg class="message-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p style="margin: 0; font-weight: 700;">¡Mensaje Enviado con Éxito!</p>
                        <p style="margin: 0; font-size: 0.8rem; font-weight: 300;">Nos pondremos en contacto contigo pronto.
                        </p>
                    </div>
                </div>
            </div>

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

    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $reason = sanitize_text_field($_POST['reason']);
    $message = sanitize_textarea_field($_POST['message']);

    // Determine recipient based on reason
    $to = get_option('admin_email');
    $reason_map = array(
        'publicidad_programa' => 'vcf_email_publicidad_programa',
        'consulta_compra' => 'vcf_email_consulta_compra',
        'reclamo' => 'vcf_email_reclamo',
        'informacion_noticiosa' => 'vcf_email_informacion_noticiosa',
    );

    if (isset($reason_map[$reason])) {
        $specific_email = get_option($reason_map[$reason]);
        if (!empty($specific_email) && is_email($specific_email)) {
            $to = $specific_email;
        }
    }

    $subject = 'Nuevo mensaje de contacto: ' . $reason;
    $body = "Nombre: $name\n";
    $body .= "Email: $email\n";
    $body .= "Teléfono: $phone\n";
    $body .= "Motivo: $reason\n\n";
    $body .= "Mensaje:\n$message\n";
    $headers = array('Content-Type: text/plain; charset=UTF-8', 'From: ' . $name . ' <' . $email . '>');

    $sent = wp_mail($to, $subject, $body, $headers);

    if ($sent) {
        $thank_you_page_id = get_option('vcf_thank_you_page_id');
        $redirect_url = $thank_you_page_id ? get_permalink($thank_you_page_id) : '';

        wp_send_json_success(array('redirect_url' => $redirect_url));
    } else {
        wp_send_json_error(array('message' => 'No se pudo enviar el correo.'));
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
