<?php

namespace FluentFormPro\Components;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\Framework\Helpers\ArrayHelper as Arr;
use FluentForm\App\Services\FormBuilder\BaseFieldManager;

class SaveProgressButton extends BaseFieldManager
{
    public function __construct()
    {
        parent::__construct(
            'save_progress_button',
            'Save & Resume',
            ['save', 'button', 'progress'],
            'advanced'
        );
        $this->updateExistingFields();
        
        add_action('wp_enqueue_scripts', function () {
            $vars = apply_filters('fluentform/save_progress_vars', [
                'source_url'            => home_url($_SERVER['REQUEST_URI']),
                'nonce'                 => wp_create_nonce(),
                'copy_button'           => sprintf("<img src='%s' >", fluentformMix('img/copy.svg')),
                'copy_success_button'   => sprintf("<img src='%s' >", fluentformMix('img/check.svg')),
                'email_button'          => sprintf("<img src='%s' >", fluentformMix('img/email.svg')),
                'email_placeholder_str' => __('Your Email Here', 'fluentformpro'),
            ]);
            wp_localize_script('form-save-progress', 'form_state_save_vars', $vars);
        });
    }
    
    public function pushFormInputType($types)
    {
        return $types;
    }
    
    function getComponent()
    {
        return [
            'index'          => 15,
            'element'        => $this->key,
            'attributes'     => [
                'class' => '',
            ],
            'settings'       => [
                'button_style'       => 'default',
                'button_size'        => 'md',
                'align'              => 'left',
                'container_class'    => '',
                'current_state'      => 'normal_styles',
                'background_color'   => 'rgb(64, 158, 255)',
                'color'              => 'rgb(255, 255, 255)',
                'hover_styles'       => (object)[
                    'backgroundColor' => '#ffffff',
                    'borderColor'     => '#1a7efb',
                    'color'           => '#1a7efb',
                    'borderRadius'    => '',
                    'minWidth'        => '100%'
                ],
                'normal_styles'      => (object)[
                    'backgroundColor' => '#1a7efb',
                    'borderColor'     => '#1a7efb',
                    'color'           => '#ffffff',
                    'borderRadius'    => '',
                    'minWidth'        => ''
                ],
                'button_ui'          => (object)[
                    'text'    => 'Save & Resume',
                    'type'    => 'default',
                    'img_url' => ''
                ],
                'conditional_logics' => [],
                'email_resume_link_enabled' => false,
                'save_success_message' => __('Your progress has been successfully saved. Resume anytime using the link below.','fluentformpro'),
                'email_subject' => sprintf(__('Resume Form Submission : %s','fluentformpro'), '{form_name}'),
                'email_body'    => self::getEmailBody()
            ],
            'editor_options' => [
                'title'      => $this->title,
                'icon_class' => 'dashicons dashicons-arrow-right-alt',
                'template'   => 'customButton'
            ],
        ];
    }
    
    public function pushConditionalSupport($conditonalItems)
    {
        return $conditonalItems;
    }
    
    
    public function getGeneralEditorElements()
    {
        return [
            'btn_text',
            'button_ui',
            'button_style',
            'button_size',
            'align',
            'save_success_message',
            'email_resume_link_enabled',
            'email_subject',
            'email_body',
            
        ];
    }
    
    public function getAdvancedEditorElements()
    {
        return [
            'container_class',
            'class',
            'conditional_logics',
        ];
    }

    public function generalEditorElement()
    {
        return [
            'save_success_message' => [
                'template'  => 'inputTextarea',
                'label'     => __('Success Message', 'fluentform'),
                'help_text'  => __('Message to show after saving form state.', 'fluentformpro'),

            ],
            'email_resume_link_enabled' => [
                'template'  => 'radioButton',
                'label'     => __('Enable Email Link', 'fluentform'),
                'help_text' => __('Allow User to Email Resume Link', 'fluentform'),
                'options'   => [
                    [
                        'value' => true,
                        'label' => __('Yes', 'fluentform'),
                    ],
                    [
                        'value' => false,
                        'label' => __('No', 'fluentform'),
                    ],
                ],
            ],
            'email_subject' => [
                'template'  => 'inputText',
                'label'     => __('Email Subject', 'fluentform'),
                'help_text'  => sprintf(__('Use %s placeholder to get the Form Name.', 'fluentformpro'),'{form_name}'),
                'dependency' => [
                    'depends_on' => 'settings/email_resume_link_enabled',
                    'value'      => true,
                    'operator'   => '==',
                ],
            ],
            'email_body' => [
                'template'  => 'inputHTML',
                'label'     => __('Email Body', 'fluentform'),
                'hide_extra' => 'yes',
                'inline_help_text'  => sprintf(__('Use %s placeholder to get the email resume link.', 'fluentformpro'),'{email_resume_link}'),
                'dependency' => [
                    'depends_on' => 'settings/email_resume_link_enabled',
                    'value'      => true,
                    'operator'   => '==',
                ],
            ],
        ];
    }

    public function render($data, $form)
    {
        $elementName = $data['element'];
        $data = apply_filters_deprecated(
            'fluentform_rendering_field_data_' . $elementName,
            [
                $data,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/rendering_field_data_' . $elementName,
            'Use fluentform/rendering_field_data_' . $elementName . ' instead of fluentform_rendering_field_data_' . $elementName
        );
        $data = apply_filters('fluentform/rendering_field_data_' . $elementName, $data, $form);
       
        wp_enqueue_script('form-save-progress');
        
        add_filter('fluentform/form_class', function ($formClass){
            return $formClass .= ' ff-form-has-save-progress';
        });
        
        $btnStyle = Arr::get($data['settings'], 'button_style');
        
        $btnSize = 'ff-btn-';
        $btnSize .= isset($data['settings']['button_size']) ? $data['settings']['button_size'] : 'md';
        $oldBtnType = isset($data['settings']['button_style']) ? '' : ' ff-btn-primary ';
        
        $align = 'ff-el-group ff-text-' . @$data['settings']['align'];
        
        $btnClasses = [
            'ff-btn ff-btn-save-progress',
            $oldBtnType,
            $btnSize,
            $data['attributes']['class']
        ];
        
        if($btnStyle == 'no_style') {
            $btnClasses[] = 'ff_btn_no_style';
        } else {
            $btnClasses[] = 'ff_btn_style';
        }
        
        $data['attributes']['class'] = trim(implode(' ', array_filter($btnClasses)));
        
        if($tabIndex = \FluentForm\App\Helpers\Helper::getNextTabIndex()) {
            $data['attributes']['tabindex'] = $tabIndex;
        }
        $styles = '';
        if (Arr::get($data, 'settings.button_style') == '') {
            $data['attributes']['class'] .= ' wpf_has_custom_css';
            // it's a custom button
            $buttonActiveStyles = Arr::get($data, 'settings.normal_styles', []);
            $buttonHoverStyles = Arr::get($data, 'settings.hover_styles', []);
            
            $activeStates = '';
            foreach ($buttonActiveStyles as $styleAtr => $styleValue) {
                if (!$styleValue) {
                    continue;
                }
                if ($styleAtr == 'borderRadius') {
                    $styleValue .= 'px';
                }
                $activeStates .= ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '-$0', $styleAtr)), '_') . ':' . $styleValue . ';';
            }
            if ($activeStates) {
                $styles .= 'form.fluent_form_' . $form->id . ' .wpf_has_custom_css.ff-btn-save-progress { ' . $activeStates . ' }';
            }
            $hoverStates = '';
            foreach ($buttonHoverStyles as $styleAtr => $styleValue) {
                if (!$styleValue) {
                    continue;
                }
                if ($styleAtr == 'borderRadius') {
                    $styleValue .= 'px';
                }
                $hoverStates .= ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '-$0', $styleAtr)), '-') . ':' . $styleValue . ';';
            }
            if ($hoverStates) {
                $styles .= 'form.fluent_form_' . $form->id . ' .wpf_has_custom_css.ff-btn-save-progress:hover { ' . $hoverStates . ' } ';
            }
        } else if($btnStyle != 'no_style') {
            $styles .= 'form.fluent_form_' . $form->id . ' .ff-btn-save-progress { background-color: ' . Arr::get($data, 'settings.background_color') . '; color: ' . Arr::get($data, 'settings.color') . '; }';
        }

        if (Arr::get($data, 'settings.email_resume_link_enabled')) {
            $data['attributes']['class'] .= ' ff_resume_email_enabled';
        }
        
        $atts = $this->buildAttributes($data['attributes']);
        $hasConditions = $this->hasConditions($data) ? 'has-conditions ' : '';
        $cls = trim($align . ' ' . $data['settings']['container_class'] . ' ' . $hasConditions);
        
        $html = "<div class='{$cls} ff_submit_btn_wrapper ff_submit_btn_wrapper_custom'>";
        
        // ADDED IN v1.2.6 - updated in 1.4.4
        if (isset($data['settings']['button_ui'])) {
            if ($data['settings']['button_ui']['type'] == 'default') {
                $html .= '<button ' . $atts . '>' . $data['settings']['button_ui']['text'] . '</button>';
            } else {
                $html .= "<button class='ff-btn-save-progress' type='submit'><img style='max-width: 200px;' src='{$data['settings']['button_ui']['img_url']}' alt='Submit Form'></button>";
            }
        } else {
            $html .= '<button ' . $atts . '>' . $data['settings']['btn_text'] . '</button>';
        }
        
        if ($styles) {
            $html .= '<style>' . $styles . '</style>';
        }
        
        $html .= '</div>';
    
        $html = apply_filters_deprecated(
            'fluentform_rendering_field_html_' . $elementName,
            [
                $html,
                $data,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/rendering_field_html_' . $elementName,
            'Use fluentform/rendering_field_html_' . $elementName . ' instead of fluentform_rendering_field_html_' . $elementName
        );

        echo apply_filters('fluentform/rendering_field_html_' . $elementName, $html, $data, $form);
    }
    
    private function getEmailBody()
    {
        return '<p>Hello,</p>
                <p>Your Progress has been saved as you have not completed filling out the {form_name}. Continue where you left off using the link below.</p>
                <p><a style="color: #ffffff; background-color: #3f9eff; text-decoration: none; font-weight: normal; font-style: normal; padding: 0.5rem 1rem; border-color: #0072ff;"
                       href="{email_resume_link}">
                        Resume Form
                    </a></p>
                <p>Thank you</p>';
    }
    
    private function updateExistingFields()
    {
        add_filter('fluentform/editor_init_element_save_progress_button', function ($element) {
            if (!isset($element['settings']['email_resume_link_enabled'])) {
                $element['settings']['email_resume_link_enabled'] = false;
            }
            if (!isset($element['settings']['save_success_message'])) {
                $element['settings']['save_success_message'] = __('Your progress has been successfully saved. Resume anytime using the link below.', 'fluentformpro');
            }
            if (!isset($element['settings']['email_subject'])) {
                $element['settings']['email_subject'] = sprintf(__('Resume Form Submission : %s',
                    'fluentformpro'), '{form_name}');
            }
            if (!isset($element['settings']['email_body'])) {
                $element['settings']['email_body'] = $this->getEmailBody();
            }
            return $element;
        });
    }
}

