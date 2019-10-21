<?php

namespace App\Http\Controllers;

use App\Conversation;
use App\Option;
use App\User;
use Illuminate\Http\Request;
use Validator;

class SettingsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * General settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function view($section = 'general')
    {
        $settings = $this->getSectionSettings($section);

        if (!$settings) {
            abort(404);
        }

        $sections = $this->getSections();

        $template_vars = [
            'settings'     => $settings,
            'section'      => $section,
            'sections'     => $this->getSections(),
            'section_name' => $sections[$section]['title'],
        ];
        $template_vars = $this->getTemplateVars($section, $template_vars);

        return view('settings/view', $template_vars);
    }

    public function getValidator($section)
    {
        $rules = $this->getSectionParams($section, 'validator_rules');

        if (!empty($rules)) {
            return Validator::make(request()->all(), $rules);
        }
    }

    public function getTemplateVars($section, $template_vars)
    {
        $section_vars = $this->getSectionParams($section, 'template_vars');

        if ($section_vars && is_array($section_vars)) {
            return array_merge($template_vars, $section_vars);
        } else {
            return $template_vars;
        }
    }

    /**
     * Parameters of the sections settings.
     *
     * If in settings parameter `env` is set, option will be saved into .env file
     * instead of DB.
     *
     * @param [type] $section [description]
     * @param string $param   [description]
     *
     * @return [type] [description]
     */
    public function getSectionParams($section, $param = '')
    {
        $params = [];

        switch ($section) {
            case 'emails':
                $params = [
                    'template_vars' => [
                        'sendmail_path' => ini_get('sendmail_path'),
                        'mail_drivers'  => [
                            'mail'     => __("PHP's mail() function"),
                            'sendmail' => __('Sendmail'),
                            'smtp'     => 'SMTP',
                        ],
                    ],
                    'validator_rules' => [
                        'settings.mail_from' => 'required|email',
                    ],
                ];
                break;
            case 'general':
                $params = [
                    'settings' => [
                        'locale' => [
                            'env' => 'APP_LOCALE',
                        ],
                        'timezone' => [
                            'env' => 'APP_TIMEZONE',
                        ],
                    ],
                ];
                break;
            case 'alerts':
                $params = [
                    'template_vars' => [
                        'logs' => \App\ActivityLog::getAvailableLogs(),
                    ],
                    'settings' => [
                        'alert_logs' => [
                            'env' => 'APP_ALERT_LOGS',
                        ],
                        'alert_logs_period' => [
                            'env' => 'APP_ALERT_LOGS_PERIOD',
                        ],
                    ],
                ];

                // todo: monitor App Logs
                foreach ($params['template_vars']['logs'] as $i => $log) {
                    if ($log == \App\ActivityLog::NAME_APP_LOGS || $log == \App\ActivityLog::NAME_OUT_EMAILS) {
                        unset($params['template_vars']['logs'][$i]);
                    }
                }

                break;
            default:
                $params = \Eventy::filter('settings.section_params', $params, $section);
                break;
        }

        if ($param) {
            if (isset($params[$param])) {
                return $params[$param];
            } else {
                return;
            }
        } else {
            return $params;
        }
    }

    public function getSectionSettings($section)
    {
        $settings = [];

        switch ($section) {
            case 'general':
                $settings = [
                    'company_name'         => Option::get('company_name', \Config::get('app.name')),
                    'next_ticket'          => (Option::get('next_ticket') >= Conversation::max('number') + 1) ? Option::get('next_ticket') : Conversation::max('number') + 1,
                    'user_permissions'     => Option::get('user_permissions', []),
                    'email_branding'       => Option::get('email_branding'),
                    'open_tracking'        => Option::get('open_tracking'),
                    'enrich_customer_data' => Option::get('enrich_customer_data'),
                    'time_format'          => Option::get('time_format', User::TIME_FORMAT_24),
                    'locale'               => \Helper::getRealAppLocale(),
                    'timezone'             => config('app.timezone'),
                ];
                break;
            case 'emails':
                $settings = [
                    'mail_from'       => \App\Misc\Mail::getSystemMailFrom(),
                    'mail_driver'     => Option::get('mail_driver', \Config::get('mail.driver')),
                    'mail_host'       => Option::get('mail_host', \Config::get('mail.host')),
                    'mail_port'       => Option::get('mail_port', \Config::get('mail.port')),
                    'mail_username'   => Option::get('mail_username', \Config::get('mail.username')),
                    'mail_password'   => Option::get('mail_password', \Config::get('mail.password')),
                    'mail_encryption' => Option::get('mail_encryption', \Config::get('mail.encryption')),
                ];
                break;
            case 'alerts':
                $settings = Option::getOptions([
                    'alert_recipients',
                    'alert_fetch',
                    'alert_fetch_period',
                    'alert_logs',
                    'alert_logs_names',
                    'alert_logs_period',
                ], [
                    'alert_logs_names'  => [],
                    'alert_logs'        => config('app.alert_logs'),
                    'alert_logs_period' => config('app.alert_logs_period'),
                ]);
                break;
            default:
                $settings = \Eventy::filter('settings.section_settings', $settings, $section);
                break;
        }

        return $settings;
    }

    public function getSections()
    {
        $sections = [
            // todo: order
            'general' => ['title' => __('General'), 'icon' => 'cog', 'order' => 100],
            'emails'  => ['title' => __('Mail Settings'), 'icon' => 'transfer', 'order' => 200],
            'alerts'  => ['title' => __('Alerts'), 'icon' => 'bell', 'order' => 300],
        ];
        $sections = \Eventy::filter('settings.sections', $sections);

        return $sections;
    }

    /**
     * Save general settings.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function save($section = 'general')
    {
        $settings = $this->getSectionSettings($section);

        if (!$settings) {
            abort(404);
        }

        return $this->processSave($section, array_keys($settings));
    }

    public function processSave($section, $settings)
    {
        // Validate
        $validator = $this->getValidator($section);

        if ($validator && $validator->fails()) {
            return redirect()->route('settings', ['section' => $section])
                        ->withErrors($validator)
                        ->withInput();
        }

        $request = request();

        $request = \Eventy::filter('settings.before_save', $request, $section, $settings);

        $cc_required = false;
        $settings_params = $this->getSectionParams($section, 'settings');
        foreach ($settings as $i => $option_name) {
            // Option has to be saved to .env file.
            if (!empty($settings_params[$option_name]) && !empty($settings_params[$option_name]['env'])) {
                $env_value = $request->settings[$option_name] ?? '';
                \Helper::setEnvFileVar($settings_params[$option_name]['env'], $env_value);
                config($option_name, $env_value);
                $cc_required = true;
                continue;
            }

            // By some reason isset() does not work for empty elements.
            if (array_key_exists($option_name, $request->settings)) {
                $option_value = $request->settings[$option_name];
                Option::set($option_name, $option_value);
            } else {
                // If option does not exist, default will be used,
                // so we can not just remove bool settings.
                if (\Option::getDefault($option_name, null) === true) {
                    Option::set($option_name, false);
                } elseif (is_array(\Option::getDefault($option_name, -1))) {
                    Option::set($option_name, []);
                } else {
                    Option::remove($option_name);
                }
            }
        }

        // Clear cache if some options have been saved to .env file.
        if ($cc_required) {
            \Helper::clearCache();
        }

        \Session::flash('flash_success_floating', __('Settings updated'));

        return redirect()->route('settings', ['section' => $section]);
    }

    /**
     * Users ajax controller.
     */
    public function ajax(Request $request)
    {
        $response = [
            'status' => 'error',
            'msg'    => '', // this is error message
        ];

        $user = auth()->user();

        switch ($request->action) {

            // Test sending emails from mailbox
            case 'send_test':

                if (empty($request->to)) {
                    $response['msg'] = __('Please specify recipient of the test email');
                }

                if (!$response['msg']) {
                    $test_result = false;

                    try {
                        $test_result = \MailHelper::sendTestMail($request->to);
                    } catch (\Exception $e) {
                        $response['msg'] = $e->getMessage();
                    }

                    if (!$test_result && !$response['msg']) {
                        $response['msg'] = __('Error occurend sending email. Please check your mail server logs for more details.');
                    }
                }

                if (!$response['msg']) {
                    $response['status'] = 'success';
                }

                // Remember email address
                if (!empty($request->to)) {
                    \App\Option::set('send_test_to', $request->to);
                }
                break;

            default:
                $response['msg'] = 'Unknown action';
                break;
        }

        if ($response['status'] == 'error' && empty($response['msg'])) {
            $response['msg'] = 'Unknown error occured';
        }

        return \Response::json($response);
    }
}
