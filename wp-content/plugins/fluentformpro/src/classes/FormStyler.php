<?php

namespace FluentFormPro\classes;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Acl\Acl;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\Framework\Helpers\ArrayHelper;
use FluentFormPro\Payments\PaymentHelper;
use FluentForm\Framework\Request\File;


class FormStyler
{
    /**
     * @var mixed
     */
    private $app;
    /**
     * @var mixed
     */
    private $request;
    
    public function __construct()
    {
        $this->app = wpFluentForm();
        $this->request = $this->app['request'];
    }
    
    public function boot()
    {
        add_action('wp_enqueue_scripts', [$this, 'addAdvancedCSS']);
        
        $this->app->addAction('fluentform/form_styler', [$this, 'initStyler']);
        $this->app->addAction('fluentform/init_custom_stylesheet', [$this, 'insertCustomStyle'], 10, 2);
        $this->app->addAction('fluentform/form_imported', [$this, 'maybeGenerateStyle'], 10, 1);
        $this->app->addAction('fluentform/form_duplicated', [$this, 'maybeGenerateStyle'], 10, 1);
    
        $this->app->addAdminAjaxAction('fluentform_save_form_styler', [$this, 'saveStylerSettings']);
        $this->app->addAdminAjaxAction('fluentform_get_form_styler', [$this, 'getStylerSettings']);
        $this->app->addAdminAjaxAction('fluentform_export_form_style',[$this, 'export'] );
        $this->app->addAdminAjaxAction('fluentform_import_form_style',[$this, 'import'] );
    
    }

    public function insertCustomStyle($selectedStyle, $formId)
    {
    
        if ($selectedStyle) {
            add_filter('fluentform/form_class', function ($formClass, $form) use ($formId, $selectedStyle) {
                if ($form->id == $formId) {
                    $formClass .= ' ' . $selectedStyle;
                }
                return $formClass;
            }, 10, 2);
    
           
            //@Todo Remove this style generated check after next version
            $styleGenerated = Helper::getFormMeta($formId, '_ff_form_styles_generated') == 'yes';
            if (!$styleGenerated ) {
                $this->generateStyleFromPreset($formId,$selectedStyle);
            }
        }

    }

    public function addAdvancedCSS()
    {
        if (isset($_GET['fluent_forms_pages']) && isset($_GET['preview_id'])) {
            add_action('wp_head', function () {
                do_action_deprecated(
                    'fluentform_load_form_assets',
                    [
                        intval($_GET['preview_id'])
                    ],
                    FLUENTFORM_FRAMEWORK_UPGRADE,
                    'fluentform/load_form_assets',
                    'Use fluentform/load_form_assets instead of fluentform_load_form_assets.'
                );
                do_action('fluentform/load_form_assets', intval($_GET['preview_id']));
            }, 99);
        }
    }

    public function initStyler($formId)
    {
        wp_enqueue_style('fluentform_styler', FLUENTFORMPRO_DIR_URL . 'public/css/styler_app.css', array(), FLUENTFORMPRO_VERSION);
        wp_enqueue_script('fluentform_styler', FLUENTFORMPRO_DIR_URL . 'public/js/styler_app.js', array('jquery'), FLUENTFORMPRO_VERSION);
        wp_enqueue_style('dashicons');

        wp_localize_script('fluentform_styler', 'fluent_styler_vars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'form_id' => $formId,
            'nonce' => wp_create_nonce(),
        ]);

        echo '<div id="ff_form_styler"><ff-styler-app :form_vars="form_vars"></ff-styler-app></div>';
    }

    public function getStylerSettings()
    {
        Acl::verify('fluentform_forms_manager');
        $formId = intval($_REQUEST['form_id']);
        $presetStyle = Helper::getFormMeta($formId, '_ff_selected_style', '');
        $styles = Helper::getFormMeta($formId, '_ff_form_styles', []);
        $preSets = $this->getPresets();
    
        $returnData = [
            'has_stripe_inline_element' => ArrayHelper::get(PaymentHelper::getStripeInlineConfig($formId), 'is_inline',
                false),
            'preset_style'              => $presetStyle,
            'styles'                    => $styles,
            'presets'                   => $preSets,
            'is_multipage'              => Helper::isMultiStepForm($formId),
            'has_section_break'         => Helper::hasFormElement($formId, 'section_break'),
            'has_html_input'            => Helper::hasFormElement($formId, 'custom_html'),
            'has_tabular_grid'          => Helper::hasFormElement($formId, 'tabular_grid'),
            'has_range_slider'          => Helper::hasFormElement($formId, 'rangeslider'),
            'has_net_promoter'          => Helper::hasFormElement($formId, 'net_promoter_score'),
            'has_payment_summary'       => Helper::hasFormElement($formId, 'payment_summary_component'),
            'has_payment_coupon'       => Helper::hasFormElement($formId, 'payment_coupon')
        ];
        
        if(!empty($_REQUEST['with_all_form_styles'])) {
            $returnData['existing_form_styles'] = $this->getOtherFormStyles($formId);
        }

        wp_send_json_success($returnData, 200);
    }

    public function saveStylerSettings()
    {
        $this->saveStyle();
    
        wp_send_json_success([
            'message' => __('Styles successfully updated', 'fluentformpro')
        ], 200);
    }

    public function getPresets()
    {
        // to do check css file with current style generated from JSON
    
        $presets = [
            ''              => [
                'label' => __('Default', ''),
                'style' => '[]',
            ],
            'ffs_modern_b'  => [
                'label' => __('Modern (Bold)', ''),
                'style' => '{"container_styles":{"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"color":{"label":"Color","element":"ff_color","value":""},"margin":{"label":"Form Margin","element":"ff_around_item","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"padding":{"label":"Form Padding","element":"ff_around_item","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"border":{"label":"Form Border Settings","element":"ff_border_config","status_label":"Enable Form Border","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":"no"}}},"asterisk_styles":{"color_asterisk":{"label":"Color","element":"ff_color","value":""}},"inline_error_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"success_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","element":"ff_boxshadow_config","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}}},"error_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","element":"ff_boxshadow_config","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}}},"label_styles":{"color":{"label":"Color","element":"ff_color","value":"rgba(66, 67, 68, 1)"},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"input_styles":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"normal":{"key":"normal","label":"Normal","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","key":"boxshadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","status_label":"Use custom Border style","key":"border","value":{"border_radius":{"top":"5","left":"5","right":"5","bottom":"5","linked":"yes"},"border_width":{"top":"2","left":"2","right":"2","bottom":"2","linked":"yes"},"border_type":"solid","border_color":"rgba(117, 117, 117, 1)","status":"yes"}}}},"focus":{"key":"focus","label":"Focus","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","key":"boxshadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","status_label":"Use custom Border style","key":"border","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(79, 75, 75, 1)","status":"no"}}}}}}},"placeholder_styles":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"submit_button_style":{"allignment":{"label":"Allignment","element":"ff_allignment_item","value":""},"all_tabs":{"label":"","element":"ff_tabs","tabs":{"normal":{"key":"normal","label":"Normal","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"width":{"label":"Button Width","key":"width","value":""},"padding":{"label":"Form Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"typography":{"key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"key":"boxshadow","label":"Box Shadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"4","left":"4","right":"4","bottom":"4","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":"yes"}}}},"hover":{"key":"hover","label":"Hover","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"key":"boxshadow","label":"Box Shadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":""}}}}},"status":"yes"}},"radio_checkbox_style":{"radio_checkbox":{"label":"","element":"ff_radio_checkbox","status_label":"Enable Smart UI","value":{"color":{"label":"Border Color","value":""},"active_color":{"label":"Checked Background Color","value":""},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"2","left":"2","right":"2","bottom":"2","linked":"yes"},"border_type":"solid","border_color":"rgba(117, 117, 117, 1)","status":"yes"}}},"status":"yes"},"color":{"label":"Items Color","element":"ff_color","value":""}},"sectionbreak_styles":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"LabelStyling":{"key":"LabelStyling","label":"Label Styling","value":{"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"padding":{"label":"Section Break Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"Section Break Margin","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"}}},"DescriptionStyling":{"key":"DescriptionStyling","label":"Description Styling","value":{"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"padding":{"label":"Section Break Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"Section Break Margin","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"}}}}}},"gridtable_style":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"TableHead":{"key":"tableHead","label":"Table Head","value":{"color":{"label":"Color","key":"color","value":""},"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}}},"TableBody":{"key":"tableBody","label":"Table Body","value":{"color":{"label":"Color","key":"color","value":""},"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}}}}}}}',
            ],
            'ffs_modern_l'  => [
                'label' => __('Modern (Light)', ''),
                'style' => '{"container_styles":{"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"color":{"label":"Color","element":"ff_color","value":""},"margin":{"label":"Form Margin","element":"ff_around_item","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"padding":{"label":"Form Padding","element":"ff_around_item","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"border":{"label":"Form Border Settings","element":"ff_border_config","status_label":"Enable Form Border","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":"no"}}},"asterisk_styles":{"color_asterisk":{"label":"Color","element":"ff_color","value":""}},"inline_error_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"success_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","element":"ff_boxshadow_config","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}}},"error_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","element":"ff_boxshadow_config","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}}},"label_styles":{"color":{"label":"Color","element":"ff_color","value":"rgba(66, 67, 68, 1)"},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"input_styles":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"normal":{"key":"normal","label":"Normal","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","key":"boxshadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","status_label":"Use custom Border style","key":"border","value":{"border_radius":{"top":"5","left":"5","right":"5","bottom":"5","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(116, 108, 108, 1)","status":"yes"}}}},"focus":{"key":"focus","label":"Focus","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","key":"boxshadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","status_label":"Use custom Border style","key":"border","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(79, 75, 75, 1)","status":"no"}}}}}}},"placeholder_styles":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"submit_button_style":{"allignment":{"label":"Allignment","element":"ff_allignment_item","value":""},"all_tabs":{"label":"","element":"ff_tabs","tabs":{"normal":{"key":"normal","label":"Normal","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"width":{"label":"Button Width","key":"width","value":""},"padding":{"label":"Form Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"typography":{"key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"key":"boxshadow","label":"Box Shadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"4","left":"4","right":"4","bottom":"4","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":"yes"}}}},"hover":{"key":"hover","label":"Hover","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"key":"boxshadow","label":"Box Shadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":""}}}}},"status":"yes"}},"radio_checkbox_style":{"radio_checkbox":{"label":"","element":"ff_radio_checkbox","status_label":"Enable Smart UI","value":{"color":{"label":"Border Color","value":""},"active_color":{"label":"Checked Background Color","value":""},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(117, 117, 117, 1)","status":"yes"}}},"status":"yes"},"color":{"label":"Items Color","element":"ff_color","value":""}},"sectionbreak_styles":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"LabelStyling":{"key":"LabelStyling","label":"Label Styling","value":{"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"padding":{"label":"Section Break Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"Section Break Margin","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"}}},"DescriptionStyling":{"key":"DescriptionStyling","label":"Description Styling","value":{"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"padding":{"label":"Section Break Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"Section Break Margin","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"}}}}}},"gridtable_style":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"TableHead":{"key":"tableHead","label":"Table Head","value":{"color":{"label":"Color","key":"color","value":""},"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}}},"TableBody":{"key":"tableBody","label":"Table Body","value":{"color":{"label":"Color","key":"color","value":""},"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}}}}}}}',
        
            ],
            'ffs_classic'   => [
                'label' => __('Classic', ''),
                'style' => '{"container_styles":{"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"color":{"label":"Color","element":"ff_color","value":""},"margin":{"label":"Form Margin","element":"ff_around_item","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"padding":{"label":"Form Padding","element":"ff_around_item","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"border":{"label":"Form Border Settings","element":"ff_border_config","status_label":"Enable Form Border","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":"no"}}},"asterisk_styles":{"color_asterisk":{"label":"Color","element":"ff_color","value":""}},"inline_error_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"success_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","element":"ff_boxshadow_config","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}}},"error_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","element":"ff_boxshadow_config","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}}},"label_styles":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"input_styles":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"normal":{"key":"normal","label":"Normal","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","key":"boxshadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","status_label":"Use custom Border style","key":"border","value":{"border_radius":{"top":"0","left":"0","right":"0","bottom":"0","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(116, 108, 108, 1)","status":"yes"}}}},"focus":{"key":"focus","label":"Focus","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","key":"boxshadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","status_label":"Use custom Border style","key":"border","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(79, 75, 75, 1)","status":"no"}}}}}}},"placeholder_styles":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"submit_button_style":{"allignment":{"label":"Allignment","element":"ff_allignment_item","value":""},"all_tabs":{"label":"","element":"ff_tabs","tabs":{"normal":{"key":"normal","label":"Normal","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"width":{"label":"Button Width","key":"width","value":""},"padding":{"label":"Form Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"typography":{"key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"key":"boxshadow","label":"Box Shadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":"yes"}}}},"hover":{"key":"hover","label":"Hover","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"key":"boxshadow","label":"Box Shadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":""}}}}},"status":"yes"}},"radio_checkbox_style":{"radio_checkbox":{"label":"","element":"ff_radio_checkbox","status_label":"Enable Smart UI","value":{"color":{"label":"Border Color","value":""},"active_color":{"label":"Checked Background Color","value":""},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"#212529","status":"yes"}}},"status":"yes"},"color":{"label":"Items Color","element":"ff_color","value":""}},"sectionbreak_styles":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"LabelStyling":{"key":"LabelStyling","label":"Label Styling","value":{"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"padding":{"label":"Section Break Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"Section Break Margin","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"}}},"DescriptionStyling":{"key":"DescriptionStyling","label":"Description Styling","value":{"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"padding":{"label":"Section Break Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"Section Break Margin","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"}}}}}},"gridtable_style":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"TableHead":{"key":"tableHead","label":"Table Head","value":{"color":{"label":"Color","key":"color","value":""},"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}}},"TableBody":{"key":"tableBody","label":"Table Body","value":{"color":{"label":"Color","key":"color","value":""},"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}}}}}}}',
            ],
            'ffs_bootstrap' => [
                'label' => __('Bootstrap Style', ''),
                'style' => '{"container_styles":{"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"color":{"label":"Color","element":"ff_color","value":""},"margin":{"label":"Form Margin","element":"ff_around_item","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"padding":{"label":"Form Padding","element":"ff_around_item","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"border":{"label":"Form Border Settings","element":"ff_border_config","status_label":"Enable Form Border","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":"no"}}},"asterisk_styles":{"color_asterisk":{"label":"Color","element":"ff_color","value":""}},"inline_error_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"success_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","element":"ff_boxshadow_config","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}}},"error_msg_style":{"color":{"label":"Color","element":"ff_color","value":""},"backgroundColor":{"label":"Background Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","element":"ff_boxshadow_config","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}}},"label_styles":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"input_styles":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"normal":{"key":"normal","label":"Normal","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","key":"boxshadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","status_label":"Use custom Border style","key":"border","value":{"border_radius":{"top":"4","left":"4","right":"4","bottom":"4","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(206, 212, 218, 1)","status":"yes"}}}},"focus":{"key":"focus","label":"Focus","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"label":"Box Shadow","key":"boxshadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","status_label":"Use custom Border style","key":"border","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(79, 75, 75, 1)","status":"no"}}}}}}},"placeholder_styles":{"color":{"label":"Color","element":"ff_color","value":""},"typography":{"label":"Typography","element":"ff_typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}},"submit_button_style":{"allignment":{"label":"Allignment","element":"ff_allignment_item","value":""},"all_tabs":{"label":"","element":"ff_tabs","tabs":{"normal":{"key":"normal","label":"Normal","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"width":{"label":"Button Width","key":"width","value":""},"padding":{"label":"Form Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"typography":{"key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"key":"boxshadow","label":"Box Shadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"4","left":"4","right":"4","bottom":"4","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":"yes"}}}},"hover":{"key":"hover","label":"Hover","value":{"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"color":{"label":"Color","key":"color","value":""},"typography":{"key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"boxshadow":{"key":"boxshadow","label":"Box Shadow","value":{"horizontal":{"value":"0","type":"px"},"vertical":{"value":"0","type":"px"},"blur":{"value":"0","type":"px"},"spread":{"value":"0","type":"px"},"color":"","position":""}},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_width":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"border_type":"solid","border_color":"","status":""}}}}},"status":"yes"}},"radio_checkbox_style":{"radio_checkbox":{"label":"","element":"ff_radio_checkbox","status_label":"Enable Smart UI","value":{"color":{"label":"Border Color","value":""},"active_color":{"label":"Checked Background Color","value":""},"border":{"label":"","key":"border","status_label":"Use custom Border style","value":{"border_radius":{"top":"4","left":"4","right":"4","bottom":"4","linked":"yes"},"border_width":{"top":"1","left":"1","right":"1","bottom":"1","linked":"yes"},"border_type":"solid","border_color":"rgba(33, 37, 41, 1)","status":"yes"}}},"status":"yes"},"color":{"label":"Items Color","element":"ff_color","value":""}},"sectionbreak_styles":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"LabelStyling":{"key":"LabelStyling","label":"Label Styling","value":{"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"padding":{"label":"Section Break Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"Section Break Margin","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"}}},"DescriptionStyling":{"key":"DescriptionStyling","label":"Description Styling","value":{"color":{"label":"Color","key":"color","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}},"padding":{"label":"Section Break Padding","key":"padding","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"},"margin":{"label":"Section Break Margin","key":"margin","value":{"top":"","left":"","right":"","bottom":"","linked":"yes"},"type":"px"}}}}}},"gridtable_style":{"all_tabs":{"label":"","element":"ff_tabs","tabs":{"TableHead":{"key":"tableHead","label":"Table Head","value":{"color":{"label":"Color","key":"color","value":""},"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}}},"TableBody":{"key":"tableBody","label":"Table Body","value":{"color":{"label":"Color","key":"color","value":""},"backgroundColor":{"label":"Background Color","key":"backgroundcolor","value":""},"typography":{"label":"Typography","key":"typography","value":{"fontSize":{"value":"","type":"px"},"fontWeight":"","transform":"","fontStyle":"","textDecoration":"","lineHeight":{"value":"","type":"px"},"letterSpacing":{"value":"","type":"px"}}}}}}}}}',
            ],
            'ffs_inherit_theme' => [
                'label' => __('Inherit Theme Style', 'fluentform'),
                'style' => '{}',
            ],
        ];
    
        $presets = apply_filters_deprecated(
            'fluentform_style_preses',
            [
                $presets
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/style_presets',
            'Use fluentform/style_presets instead of fluentform_style_preses.'
        );
        $presets = apply_filters('fluentform/style_presets', $presets);

        $presets['ffs_custom'] = [
            'label' => __('Custom (Advanced Customization)', ''),
            'src' => ''
        ];

        return $presets;
    }

    public function maybeGenerateStyle($formId)
    {
        // check if the form has custom styler
        $styles = Helper::getFormMeta($formId, '_ff_form_styles');
        if ($styles) {
            $stylerGenerator = new FormStylerGenerator();
            $css = $stylerGenerator->generateFormCss('.fluentform_wrapper_' . $formId, $styles);
            $this->saveGeneratedCss($formId, $css);
        }
    }

    private function getOtherFormStyles($callerFormId)
    {
        $customStyles = wpFluent()->table('fluentform_form_meta')
            ->select([
                'fluentform_form_meta.value',
                'fluentform_form_meta.form_id',
                'fluentform_forms.title'
            ])
            ->where('fluentform_form_meta.form_id', '!=', $callerFormId)
            ->where('fluentform_form_meta.meta_key', '_ff_form_styles')
            ->join('fluentform_forms', 'fluentform_forms.id', '=', 'fluentform_form_meta.form_id')
            ->get();

        $validFormSelectors = wpFluent()->table('fluentform_form_meta')
            ->select([
                'value',
                'form_id',
                'value'
            ])
            ->where('fluentform_form_meta.form_id', '!=', $callerFormId)
            ->where('fluentform_form_meta.meta_key', '_ff_selected_style')
            ->where('fluentform_form_meta.meta_key', '!=', '')
            ->get();

        $styles = [];
        foreach ($validFormSelectors as $formSelector) {
            if(!$formSelector->value || $formSelector->value == '""') {
                continue;
            }
            $selectorType = str_replace('"', '', $formSelector->value);
            $styles[$formSelector->form_id] = [
                'type' => $selectorType,
                'is_custom' => $selectorType == 'ffs_custom',
                'form_id' => $formSelector->form_id
            ];
        }

        $formattedStyles = [
            'custom' => [],
            'predefined' => []
        ];

        foreach ($customStyles as $style) {
            if(!isset($styles[$style->form_id])) {
                continue;
            }
            $existingStyle = $styles[$style->form_id];
            $existingStyle['form_title'] = $style->title;
            $existingStyle['styles'] = json_decode($style->value, true);
            if($existingStyle['is_custom']) {
                $formattedStyles['custom'][$style->form_id] = $existingStyle;
            } else {
                $formattedStyles['predefined'][$style->form_id] = $existingStyle;
            }
        }

        return $formattedStyles;
    }
    
    public function export()
    {
        try {
            $formId = intval($_REQUEST['form_id']);
            $name = sanitize_title($_REQUEST['style_name']);
            $styles = Helper::getFormMeta($formId, '_ff_form_styles');
            if(!$styles || !$formId){
                throw new \Exception('Required Parameter missing!');
            }
            $fileName = 'fluentform_style_' . $name .'.json';
            header('Content-disposition: attachment; filename=' . $fileName);
    
            header('Content-type: application/json');
    
            echo json_encode($styles); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $forms is escaped before being passed in.
    
            die();
        
        } catch (Exception $exception) {
            wp_send_json([
                'message' => $exception->getMessage()
            ], 424);
        }
    }
    
    public function import()
    {
     
        $styles = $this->request->file('file');
       
        if ($styles instanceof File) {
            $styles = \json_decode($styles->getContents(), true);
            $formId = intval($_REQUEST['form_id']);
            Helper::setFormMeta($formId, '_ff_form_styles', $styles);
    
            $stylerGenerator = new FormStylerGenerator();
            $css = $stylerGenerator->generateFormCss('.fluentform_wrapper_' . $formId, $styles);
            $css = trim($css);
            $this->saveGeneratedCss($formId, $css);
            wp_send_json([
                'success' => true
            ]);
        }
       
    
    }
    
    private function saveStyle()
    {
        Acl::verify('fluentform_forms_manager');
    
        $formId = intval($_REQUEST['form_id']);
        $styleType = sanitize_text_field($_REQUEST['style_name']);
        $styles = wp_unslash($_REQUEST['form_styles']);
        Helper::setFormMeta($formId, '_ff_selected_style', $styleType);
        Helper::setFormMeta($formId, '_ff_form_styles', $styles);
        
        $stylerGenerator = new FormStylerGenerator();
        $css = $stylerGenerator->generateFormCss('.fluentform_wrapper_' . $formId, $styles);
        $css = trim($css);
    
        $this->saveGeneratedCss($formId, $css);
    }
    
    private function saveGeneratedCss($formId, $css)
    {
        Helper::setFormMeta($formId, '_ff_form_styler_css', $css);
        do_action('fluentform/after_style_generated', $formId);
    }
    
    public function generateStyleFromPreset( $formId,$selectedStyle)
    {
        $presets = $this->getPresets();
        if (isset($presets[$selectedStyle])) {
    
            $selectedPreset = $presets[$selectedStyle];
            $styles = ArrayHelper::get($selectedPreset, 'style');
            if (is_string($styles) && Helper::isJson($styles)) {
                $styles = json_decode($styles, true);
            }
            if ('ffs_custom' == $selectedStyle) {
                $styles = Helper::getFormMeta($formId, '_ff_form_styles', []);
            }
            Helper::setFormMeta($formId, '_ff_form_styles', $styles);
            $stylerGenerator = new FormStylerGenerator();
            $css = $stylerGenerator->generateFormCss('.fluentform_wrapper_' . $formId, $styles);
            $css = trim($css);
    
            $this->saveGeneratedCss($formId, $css);
            Helper::setFormMeta($formId, '_ff_selected_style', $selectedStyle);
            Helper::setFormMeta($formId, '_ff_form_styles_generated', 'yes');
    
        }else{
            $this->saveGeneratedCss($formId, '');
        }
    }
    
}
