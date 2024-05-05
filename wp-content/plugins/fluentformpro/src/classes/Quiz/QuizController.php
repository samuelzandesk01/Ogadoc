<?php


namespace FluentFormPro\classes\Quiz;

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Modules\Acl\Acl;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\FormBuilder\ShortCodeParser;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

class QuizController
{
    public $metaKey = '_quiz_settings';
    public $key = 'quiz_addon';
    protected $app;
    
    
    public function init($app)
    {
        $this->app = $app;
        $enabled = $this->isEnabled();
        
        $this->addToGlobalMenu($enabled);
        
        if (!$enabled) {
            return;
        }
        $this->addToFormSettingsMenu();

        $this->maybeRandomize();
        
        $this->registerAjaxHandlers();
        
        add_filter('fluentform/all_editor_shortcodes', function ($shortCodes) {
            $shortCodes[] = [
                'title' => __('Quiz', 'fluentformpro'),
                'shortcodes' => [
                    '{quiz_result}' => 'Quiz Result List'
                ]
            ];
            
            return $shortCodes;
        });
        
        new QuizScoreComponent();
        
        add_filter('fluentform/shortcode_parser_callback_quiz_result', [$this, 'getQuizResultTable'], 10, 2);

        add_filter('fluentform/form_submission_confirmation', [$this, 'maybeAppendResult'], 10, 3);
        
        add_filter('fluentform/submission_cards', [$this, 'pushQuizResult'], 10, 3);
    }
    
    public function registerAjaxHandlers()
    {
        $this->app->addAdminAjaxAction('ff_get_quiz_module_settings', [$this, 'getSettingsAjax']);
        $this->app->addAdminAjaxAction('ff_store_quiz_module_settings', [$this, 'saveSettingsAjax']);
    }
    
    public function getSettingsAjax()
    {
        $formId = intval($_REQUEST['form_id']);
        Acl::verify('fluentform_forms_manager', $formId);
        $settings = $this->getSettings($formId);
        wp_send_json_success([
            'settings' => $settings,
            'quiz_fields' => $this->getQuizFields($formId),
            'settings_fields' => QuizController::getIntegrationFields(),
        ]);
    }
    
    public function saveSettingsAjax()
    {
        $formId = intval($_REQUEST['form_id']);
        Acl::verify('fluentform_forms_manager', $formId);
        $settings = $_REQUEST['settings'];
        $formattedSettings = wp_unslash($settings);
        Helper::setFormMeta($formId, $this->metaKey, $formattedSettings);
        
        wp_send_json_success([
            'message' => __('Settings successfully updated'),
        ]);
    }
    
    public function getSettings($formId)
    {
        // todo : Add Static Cache Reduce Query Calls

        $settings = Helper::getFormMeta($formId, $this->metaKey, []);
        $form = $this->getForm($formId);
        $fields = $this->getQuizFields($form);
        $defaults = [
            'enabled' => false,
            'randomize_answer' => false,
            'append_result' => true,
            'randomize_question' => false,
            'saved_quiz_fields' => $fields,
            'grades' => [
                [
                    'label' => 'A',
                    'min' => 90,
                    'max' => 100,
                ],
                [
                    'label' => 'B',
                    'min' => 80,
                    'max' => 89,
                ],
                [
                    'label' => 'C',
                    'min' => 70,
                    'max' => 79,
                ],
            ]
        ];
        
        $settings = $this->removeDeletedFields($settings, $fields);
        
        $settings = wp_parse_args($settings, $defaults);
        if (empty($settings['saved_quiz_fields'])) {
            $settings['saved_quiz_fields'] = (object)[];
        }
        return $settings;
    }
    
    protected function getForm($formId)
    {
        return wpFluent()->table('fluentform_forms')->find($formId);
    }
    
    public static function getIntegrationFields()
    {
        return [
            [
                'key' => 'append_result',
                'label' => 'Append Result',
                'component' => 'checkbox-single',
                'checkbox_label' => 'Show Result on confirmation page'
            
            ],
            [
                'key' => 'randomize_question',
                'label' => 'Randomize Questions',
                'checkbox_label' => 'Questions will be randomized each time its loaded',
                'component' => 'checkbox-single'
            ],
            [
                'key' => 'randomize_answer',
                'label' => 'Randomize Options',
                'checkbox_label' => 'Options will be randomized each time its loaded',
                'component' => 'checkbox-single'
            ],
        
        ];
    }
    
    public function isEnabled()
    {
        $globalModules = get_option('fluentform_global_modules_status');
        $quizAddon = Arr::get($globalModules, $this->key);
        
        if ($quizAddon == 'yes') {
            return true;
        }
        
        return false;
    }
    
    public function addToGlobalMenu($enabled)
    {
        add_filter('fluentform/global_addons', function ($addOns) use ($enabled) {
            $addOns[$this->key] = [
                'title' => 'Quiz Module',
                'description' => 'With this module, you can create quizzes and show scores with grades, points, fractions, or percentages',
                'logo' => fluentFormMix('img/integrations/quiz-icon.svg'),
                'enabled' => ($enabled) ? 'yes' : 'no',
                'config_url' => '',
                'category' => ''
            ];
            
            return $addOns;
        }, 9);
    }
    
    public function addToFormSettingsMenu()
    {
        add_filter('fluentform/form_settings_menu', function ($menu) {
            $menu['quiz_settings'] = [
                'title' => __('Quiz Settings', 'fluentform'),
                'slug' => 'form_settings',
                'hash' => 'quiz_settings',
                'route' => '/quiz_settings'
            ];
            
            return $menu;
        });
    }
    
    /**
     * Maybe Randomize Questions and Answers
     *
     * @return void
     */
    public function maybeRandomize()
    {
        
        add_filter('fluentform/rendering_form', function ($form) {
            $settings = $this->getSettings($form->id);
            $enabled = $settings['enabled'] == 'yes';
            if (!$enabled) {
                return $form;
            }
            if ($settings['randomize_answer']) {
                $this->randomizeCheckableInputs();
            }
            if (!$settings['randomize_question']) {
                return $form;
            }
            $fields = $form->fields;
    
            $quizFields = $this->getQuizFields($form);
            $quizFieldsKeys = array_keys($quizFields);
            $formQuizFields = array_filter($fields['fields'], function ($field) use ($quizFieldsKeys) {
                return in_array(Arr::get($field, 'attributes.name'), $quizFieldsKeys);
            });
            if (empty($formQuizFields)) {
                return $form;
            }
            $quizGroup = [];
            $i = 0;
            foreach ($formQuizFields as $key => $field) {
                $inSequence = isset($formQuizFields[$key + 1]) ? $formQuizFields[$key + 1] : false;
                if ($inSequence) {
                    $quizGroup[$i][$key] = $field;
                } else {
                    $quizGroup[$i][$key] = $field;
                    $i++;
                }
            }
            //shuffle groups and replace their positions in the original array
            foreach ($quizGroup as $group) {
                $startIndex = Arr::get(array_keys($group), '0');
                shuffle($group);
                $length = count($group);
                array_splice($fields['fields'], $startIndex, $length, $group);
            }
    
            $form->fields = $fields;
            
            return $form;
        }, 10, 1);
    }
    
    
    /**
     * Generate Quiz Result Table
     *
     * @param $shortCode
     * @param ShortCodeParser $parser
     *
     * @return string|void
     */
    public function getQuizResultTable($shortCode, ShortCodeParser $parser)
    {
        $form = $parser::getForm();
        $entry = $parser::getEntry();
        $quizSettings = $this->getSettings($form->id);
        
        if (!$entry || !$form || $quizSettings['enabled'] != 'yes') {
            return;
        }
       
        $scoreType = $this->getScoreType($form);
        $response = json_decode($entry->response, true);
        $results = $this->getFormattedResults($quizSettings, $response, $form);
        
        /* For full width in single entry page */
        $width = defined('FLUENTFORM_RENDERING_ENTRIES') ? '' : ' width="600"';
        $html = '<table class="table ff_quiz_result_table"  ' . $width . ' cellpadding="0" cellspacing="0" style="min-width: 100%"><tbody>';
        foreach ($results as $result) {
            $isCorrect = $result['correct'] ;
            $icon = $isCorrect ? self::getRightIcon() : self::getWrongIcon();
            $maybeShowicon = $scoreType == 'total_point' ? '' : "<div style='width: 20px;margin-right: 10px;'>" . $icon . " </div>";
            $html .= sprintf(
                "<tr class=\"field-label\"> <td style=\"display:flex;align-items: center; padding: 6px 12px; background-color: #f8f8f8; text-align: left;\"> %s <div><b>%s </b></div></td></tr>",
                $maybeShowicon,
                $result['label']
            );
            
            $userValueFormatted = is_array($result['user_value']) ? join(', ', $result['user_value']) : $result['user_value'];
            if ($userValueFormatted) {
                $html .= sprintf(
                    "<tr class=\"user-value\"><td style=\"padding: 6px 12px 12px 12px;\">%s</td>",
                    $userValueFormatted
                );
            }
        
            $correctAnsFormatted = is_array($result['correct_value']) ? join(', ', $result['correct_value']) : $result['correct_value'];
            //skip right wrong for when total point is selected
            if (!$isCorrect && $scoreType != 'total_point') {
                $conditionText = '';
                if ($result['correct_ans_condition'] == 'not_includes') {
                    $conditionText = 'does Not includes ';
                } elseif ($result['correct_ans_condition'] == 'includes_any') {
                    $conditionText = 'any of the following ';
                } elseif ($result['correct_ans_condition'] == 'includes_all') {
                    $conditionText = 'all of the following ';
                }
                $html .= sprintf(
                    "<tr class=\"field-value\"><td style=\"padding: 6px 12px 12px 12px;\">%s %s: %s</td>",
                    __('Correct answer', 'fluentformpro'),
                    $conditionText,
                    $correctAnsFormatted
                );
            } else if ($scoreType == 'total_point') {
                $score = 0;
                if ($result['has_advance_scoring'] == 'yes') {
                    $score = $result['advance_points_score'];
                } else {
                    if ($result['correct']) {
                        $score = $result['points'];
                    }
                }
                
                $html .= sprintf(
                    "<tr class=\"field-value\"><td style=\"padding: 6px 12px 12px 12px;\">%s : %s</td>",
                    __('Point', 'fluentformpro'),
                    $score
                );
            }
            $html .= '</tr>';
        }
    
        $html .= '</tbody></table>';
    
        $html = apply_filters_deprecated(
            'fluentform/quiz_result_table_html',
            [
                $html,
                $form,
                $results,
                $quizSettings,
                $entry
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/quiz_result_table_html',
            'Use fluentform/quiz_result_table_html instead of fluent_form_quiz_result_table_html.'
        );

        return apply_filters('fluentform/quiz_result_table_html', $html, $form, $results, $quizSettings, $entry);
    }
    
    
    /**
     * Get Available Quiz Fields
     *
     * @param $form
     *
     * @return array|mixed
     */
    protected function getQuizFields($form)
    {
        $fields = FormFieldsParser::getEntryInputs($form, ['admin_label', 'label', 'element', 'options']);
        $supportedQuizFields = [
            'input_text',
            'input_radio',
            'input_checkbox',
            'select',
            'input_number',
            'input_date',
            'rangeslider'
        ];
        $fields = array_filter($fields, function ($field) use ($supportedQuizFields) {
            return in_array($field['element'], $supportedQuizFields);
        });
        
        foreach ($fields as $name => $value) {
            $fields[$name]['enabled'] = false;
            $fields[$name]['points'] = 1;
            $fields[$name]['correct_answer'] = [];
            $fields[$name]['condition'] = 'equal';
            $fields[$name]['has_advance_scoring'] = 'no';
            $fields[$name]['advance_points'] = $this->advancePoints($fields[$name]);
        }
        return $fields;
    }
    
    /**
     * Remove Deleted inputs
     *
     * @param $settings
     * @param $fields
     *
     * @return mixed
     */
    protected function removeDeletedFields($settings, $fields)
    {
        if (!isset($settings['saved_quiz_fields'])) {
            return $settings;
        }
        $savedFields = $settings['saved_quiz_fields'];
        foreach ($savedFields as $fieldKey => $value) {
            if (!isset($fields[$fieldKey])) {
                unset($savedFields[$fieldKey]);
            }
            if (Arr::exists($fields,$fieldKey) && Arr::exists($fields[$fieldKey], 'options')) {
                $savedFields[$fieldKey]['options'] = Arr::get($fields[$fieldKey], 'options');
            }
        }
        $settings['saved_quiz_fields'] = $savedFields;
        
        return $settings;
    }
    
    
    /**
     * Validate Answer
     *
     * @param $settings
     * @param $userValue
     * @param $correctValue
     *
     * @return bool
     */

    protected function isCorrect($settings, $userValue, $correctValue = '', $options = [])
    {
        $isCorrect = false;
        $element = $settings['element'];
        switch ($element) {
            case 'input_radio':
                if (!$userValue) {
                    break;
                }
                
                if (in_array($userValue, $correctValue)) {
                    $isCorrect = true;
                }
                
                break;
            case 'select':
            case 'input_text':
            case 'rangeslider':
            case 'input_date':
            case 'input_checkbox':
            case 'input_number':
                if (!$userValue) {
                    break;
                }
                $hasAdvanceScoring = $settings['has_advance_scoring'] === 'yes';
                if ($hasAdvanceScoring) {
                    //if it has advance scoring then for right answer match all value greater than 0 with user values
                    //else assume as wrong answer but count the scores
                    $correctValues = static::labelFromValue($correctValue, $options, $settings, 'correct_value');
                  
                    return count(array_intersect($userValue, $correctValues)) == count($userValue);
                }
                $condition = Arr::get($settings, 'condition');
            
                if ($condition == 'equal') {
                    if (is_array($correctValue)) {
                        $correctValue = array_shift($correctValue);
                    }
                    if (is_array($userValue)) {
                        $userValue = array_shift($userValue);
                    }
                    if (apply_filters('fluentform/quiz_case_sensitive_off', __return_false())) {
                        $userValue = strtolower($userValue);
                        $correctValue = strtolower($correctValue);
                    }
                    if ($userValue == $correctValue) {
                        $isCorrect = true;
                    }
                } elseif ($condition == 'includes_any') {
                    //check if all user values exists in correct answers
                    if (is_array($userValue)) {
                        $isCorrect = count(array_intersect($userValue, $correctValue)) === count($userValue);
                    } elseif (in_array($userValue, $correctValue)) {
                        $isCorrect = true;
                    }
                } elseif ($condition == 'includes_all') {
                    if (!is_array($userValue)) {
                        $userValue = [$userValue];
                    }
                    $isCorrect = count(
                            array_intersect(
                                $userValue,
                                $correctValue
                            )
                        ) && count($correctValue) == count($userValue);
                } elseif ($condition == 'not_includes') {
                    if (!in_array($userValue, $correctValue)) {
                        $isCorrect = true;
                    }
                }
                break;
        }
        
        return $isCorrect;
    }
    
    /**
     * Get Formatted Quiz Result
     *
     * @param $quizFields
     * @param $response
     *
     * @return array
     */
    public function getFormattedResults($quizSettings, $response, $form)
    {
        $quizFields = $quizSettings['saved_quiz_fields'];
        $inputs = FormFieldsParser::getInputs($form, ['element', 'options', 'label']);
        $quizResults = [];
        $quizFields = $this->arrayReposition($quizFields, array_keys($inputs));
    
        foreach ($quizFields as $key => $settings) {
            if ($settings['enabled'] != true) {
                continue;
            }
            $correctValue = Arr::get($settings, 'correct_answer');
            $userValue = Arr::get($response, $key, '');
            $options = Arr::get($inputs, $key . '.options');
            $quizResults[$key] = [
                'correct'               => $this->isCorrect($settings, $userValue, $correctValue, $options),
                'correct_value'         => static::labelFromValue($correctValue, $options, $settings, 'correct_value'),
                'correct_ans_condition' => $settings['condition'],
                'options'               => $options,
                'user_value'            => static::labelFromValue($userValue, $options, $settings, 'user_value'),
                'points'                => $settings['points'],
                'label'                 => Arr::get($inputs, $key . '.label'),
                'has_advance_scoring'   => $settings['has_advance_scoring'],
                'advance_points' => array_reduce($settings['advance_points'], function ($sum, $itemScore) {
                    $sum += $itemScore;
                    return $sum;
                }),
                'advance_points_score'  => $this->calcAdvancePoints($settings, $userValue),
            ];
        }
       
        return $quizResults;
    }

    /**
     * Array Restructured to get Form Fields structure
     * @param $array
     * @param $keys
     * @return array
     */
    protected function arrayReposition($array, $keys)
    {
        $returnArray = [];
        foreach ($keys as $key) {
            if (Arr::get($array, $key)) {
                $returnArray[$key] = Arr::get($array, $key);
            }
        }

        return $returnArray;
    }

    /**
     * Get input label from value
     *
     * @param $userValue
     * @param $options
     *
     * @return array|mixed
     */
    private static function labelFromValue($targetValues, $options, $settings, $type)
    {
        $hasAdvanceScoring = $settings['has_advance_scoring'] === 'yes';
        if ($hasAdvanceScoring && $type == 'correct_value') {
            $advanceScores = Arr::get($settings, 'advance_points');
            $correctOptions = [];
            foreach ($advanceScores as $label => $score) {
                if ($score >= 1) {
                    $correctOptions[] = $label;
                }
            }
            return $correctOptions;
        }
        if (is_array($targetValues)) {
            $formattedValue = [];
            foreach ($targetValues as $value) {
                $formattedValue[] = isset($options[$value]) ? $options[$value] : $value;
            }
            $targetValues = $formattedValue;
        } else {
            $targetValues = isset($options[$targetValues]) ? $options[$targetValues] : $targetValues;
        }
        return $targetValues;
    }
    
    /**
     * Maybe Append quiz result
     *
     * @param $data
     * @param $formData
     * @param $form
     *
     * @return mixed
     */
    public function maybeAppendResult($data, $formData, $form)
    {
        $settings = $this->getSettings($form->id);
        if ($settings['append_result'] == true) {
            $data['messageToShow'] .= '{quiz_result}';
        }
        
        return $data;
    }
    
    
    /**
     * Adds quiz result in the single entry page
     * @param $cards
     * @param $entryData
     *
     * @return array|mixed
     */
    public function pushQuizResult($cards, $entryData, $submission)
    {
        $formId = $submission->form_id;
        
        $settings = $this->getSettings($formId);
        
        if (($settings['enabled'] != 'yes')) {
            return $cards;
        }
        $form = $this->getForm($formId);
        
        $contents = '<p>{quiz_result}</p>';
        $resultHtml = ShortCodeParser::parse(
            $contents,
            $submission->id,
            $entryData,
            $form
        );
        $widgetData = [
            'title' => __('Quiz Result', 'fluentformpro'),
            'content' => $resultHtml
        ];
        
        $cards['quiz_result'] = $widgetData;
        
        return $cards;
    }
    
    /**
     * Wrong Answer Icon
     *
     * @return string
     */
    protected static function getWrongIcon()
    {
        $icon = '<svg fill="#e13636" version="1.1" viewBox="0 0 32 32"  xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M17.459,16.014l8.239-8.194c0.395-0.391,0.395-1.024,0-1.414c-0.394-0.391-1.034-0.391-1.428,0  l-8.232,8.187L7.73,6.284c-0.394-0.395-1.034-0.395-1.428,0c-0.394,0.396-0.394,1.037,0,1.432l8.302,8.303l-8.332,8.286  c-0.394,0.391-0.394,1.024,0,1.414c0.394,0.391,1.034,0.391,1.428,0l8.325-8.279l8.275,8.276c0.394,0.395,1.034,0.395,1.428,0  c0.394-0.396,0.394-1.037,0-1.432L17.459,16.014z" /><g/><g/><g/><g/><g/><g/></svg>';
    
        $icon = apply_filters_deprecated(
            'fluentform_quiz_right_ans_icon',
            [
                $icon
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/quiz_wrong_ans_icon',
            'Use fluentform/quiz_wrong_ans_icon instead of fluentform_quiz_right_ans_icon.'
        );
        
        return apply_filters('fluentform/quiz_wrong_ans_icon', $icon);
    }
    
    /**
     * Right Answer Icon
     *
     * @return string
     */
    protected static function getRightIcon()
    {
        $icon = '<svg xmlns="http://www.w3.org/2000/svg"   viewBox="0 0 24 24" fill="none" stroke="#1a7efb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-check"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    
        $icon = apply_filters_deprecated(
            'fluentform_quiz_right_ans_icon',
            [
                $icon
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/quiz_right_ans_icon',
            'Use fluentform/quiz_right_ans_icon instead of fluentform_quiz_right_ans_icon.'
        );
        
        return apply_filters('fluentform/quiz_right_ans_icon', $icon);
    }
    
    public function advancePoints($options)
    {
        if (!isset($options['options'])) {
            return (object)[];
        }
        $formattedOptions = [];
        foreach ($options['options'] as $key => $value) {
            $formattedOptions[$key] = 0;
        }
        return $formattedOptions;
    }
    
    private function calcAdvancePoints($settings, $userValue)
    {
        $hasAdvancePoint = Arr::get($settings, 'has_advance_scoring');
        if ($hasAdvancePoint) {
            $advancePoints = Arr::get($settings, 'advance_points');
            $userValue = is_array($userValue) ? $userValue : [$userValue];
            $total = 0;
            foreach ($userValue as $value) {
                $total += Arr::get($advancePoints, $value, 0);
            }
            return $total;
        } else {
            return Arr::get($settings, 'points');
        }
    }
    
    private function getScoreType($form)
    {
        $scoreType = '';
        $scoreInput = \FluentForm\App\Modules\Form\FormFieldsParser::getInputsByElementTypes($form,['quiz_score'],['settings']);
        if ($scoreInput) {
            $scoreType = Arr::get(array_shift($scoreInput), 'settings.result_type');
        }
        return $scoreType;
    }
    
    public function randomizeCheckableInputs()
    {
        add_filter('fluentform/rendering_field_data_input_checkbox', function ($data) {
            $options = $data['settings']['advanced_options'];
            shuffle($options);
            $data['settings']['advanced_options'] = $options;
        
            return $data;
        }, 10, 1);
    
        add_filter('fluentform/rendering_field_data_input_radio', function ($data) {
            $options = $data['settings']['advanced_options'];
            shuffle($options);
            $data['settings']['advanced_options'] = $options;
        
            return $data;
        }, 10, 1);
    }
    
}
