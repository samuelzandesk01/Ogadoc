<?php

namespace FluentFormSignature;

class Component
{
    /**
     * Push signature editor component to the fluentform components array.
     *
     * @param  array $components
     * @return array $components
     */
    public static function push($components)
    {
        $signature = [
            'index'      => 8,
            'element'    => 'signature',
            'attributes' => [
                'name' => 'signature',
                'class' => '',
            ],
            'settings' => [
                'container_class'  => '',
                'label'            => 'Signature',
                'label_placement'  => '',
                'help_message'     => '',
	            'sign_background_color' => '#ffffff',
	            'sign_border_color' => '#FFEB3B',
	            'sign_pen_color' => '#333',
	            'sign_pen_size' => 2,
	            'sign_pad_height' => 200,
	            'sign_instruction' => 'Sign Here',
	            'admin_field_label' => 'Signature',
                'validation_rules' => [
                    'required' => [
                        'value'   => false,
                        'message' => 'This field is required',
                    ],
                ],
                'conditional_logics' => []
            ],
            'editor_options' => [
                'title'      => 'Signature',
                'icon_class' => 'ff-icon-ink-pen',
                'template'   => 'imagePlaceholder',
                'imageUrl' => FLUENTFORM_SIGNATURE_URL . 'src/assets/images/signature.png'
            ],
        ];

        $signatureComponent = apply_filters_deprecated(
            'fluentform-signature-component',
            [
                $signature
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/signature_component',
            'Use fluentform/signature_component instead of fluentform-signature-component.'
        );
        $signatureComponent = apply_filters('fluentform/signature_component', $signature);

        $components['advanced'][] = $signatureComponent;

        return $components;
    }
    
    public static function  placementSettings( $placement_settings ) {
	    $placement_settings['signature'] = array(
		    'general'  => array(
			    'label',
			    'label_placement',
			    'sign_instruction',
			    'admin_field_label',
			    'validation_rules',
		    ),
		    'advanced' => array(
				'name',
			    'sign_background_color',
			    'sign_border_color',
			    'sign_pen_color',
			    'sign_pen_size',
			    'sign_pad_height',
			    'help_message',
			    'container_class',
			    'class',
			    'conditional_logics',
		    ),
	    );
	    return $placement_settings;
    }
    
    public static function addTags($tags) {
	    $tags['signature'] = array('signature', 'stamp', 'mark', 'autograph');
	    return $tags;
    }
    
    public static function customizationSettings($customization_settings) {
    	
	    $customization_settings['sign_background_color'] = array(
		    'template'  => 'inputColor',
		    'label'     => 'Pad Background Color',
		    'help_text' => 'The Background color of the signature pad',
	    );
	    $customization_settings['sign_border_color'] = array(
		    'template'  => 'inputColor',
		    'label'     => 'Border Color',
		    'help_text' => 'The Border color of the signature pad',
	    );
	    $customization_settings['sign_pen_color'] = array(
		    'template'  => 'inputColor',
		    'label'     => 'Pen Color',
		    'help_text' => 'The Pen color of the signature pad',
	    );
	    $customization_settings['sign_pen_size'] = array(
			'type' => 'number',
			'template'  => 'inputText',
		    'label'     => 'Pen Size',
		    'help_text' => 'Size of your Pen',
	    );
	    $customization_settings['sign_instruction'] = array(
			'template'  => 'inputText',
		    'label'     => 'Sign Instruction',
		    'help_text' => 'Write Label that will show under the signature pad',
	    );
	    $customization_settings['sign_pad_height'] = array(
			'type' => 'number',
			'template'  => 'inputText',
		    'label'     => 'Pad Height',
		    'help_text' => 'Height of the signature pad',
	    );

	    return $customization_settings;
    }
}
