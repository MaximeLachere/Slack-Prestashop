<?php
/*
* Module développé par Maxime Lacheré.
* @description Permet de mettre en relation le site PrestaShop et Slack
*
**/

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once(dirname(__FILE__).'/classes/SlackAPI.php');

class Slack extends Module
{
    private $_postErrors = array();

    public function __construct()
    {
        $this->name = 'slack';
        $this->tab = 'administration';
        $this->version = '1.0';
        $this->author = 'Maxime Lacheré';
        $this->ps_versions_compliancy = array('min' => '1.6.0', 'max' => '1.6.1.14');
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Slack');
        $this->description = $this->l('This module allows the interaction between your site and slack');

        $config = Configuration::getMultiple(array('SLACK_TOKEN',
                                                   'SLACK_CHANNEL_CUSTOMER',
                                                   'SLACK_CHANNEL_ORDER',
                                                   'SLACK_CHANNEL_STOCK',
                                                   'SLACK_NEW_CUSTOMER',
                                                   'SLACK_NEW_ORDER',
                                                   'SLACK_OUT_OF_STOCK',
                                                   'SLACK_QTIES_OUT'));
    }

    public function install()
    {
        if (parent::install() == false
            || !$this->registerHook('header')
            || !$this->registerHook('actionValidateOrder')
            || !$this->registerHook('actionObjectCustomerAddAfter')
            || !$this->registerHook('actionUpdateQuantity')) {
            return false;
        } else {
            return true;
        }
    }

    public function uninstall()
    {
        if (parent::uninstall() == false) {
            return false;
        }

        Configuration::deleteByName('SLACK_TOKEN');
        Configuration::deleteByName('SLACK_CHANNEL_CUSTOMER');
        Configuration::deleteByName('SLACK_CHANNEL_ORDER');
        Configuration::deleteByName('SLACK_CHANNEL_STOCK');
        Configuration::deleteByName('SLACK_NEW_CUSTOMER');
        Configuration::deleteByName('SLACK_NEW_ORDER');
        Configuration::deleteByName('SLACK_OUT_OF_STOCK');
        Configuration::deleteByName('SLACK_QTIES_OUT');
        return true;
    }

    public function getContent()
    {
        $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        }
        $this->_html .= '<script src="'.$this->_path.'/js/slack.js"></script>';

        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function renderForm()
    {
        $options[] = array(
            'id_option' => 'undefined',
            'name' => $this->l('Choose Your Channel'),
            );

        if (!empty(Configuration::get('SLACK_TOKEN'))) {
            $channels = slackAPI::getChannelsListByToken(Configuration::get('SLACK_TOKEN'), true);

            foreach ($channels as $key => $channel) {
                $options[] = array(
                    'id_option' => $key,
                    'name' => $channel,
                    );
            }
        }

        $fields_form_1 = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Slack details'),
                    'icon'  => 'icon-envelope'
                ),
                'description' => $this->l('It is imperative to generate the token of your team slack').
                                '<br/>'.
                                 $this->l('You can generate the token by following the link: ').' 
                                 <a href="https://get.slack.help/hc/fr-fr/articles/215770388-Cr%C3%A9er-et-r%C3%A9actualiser-un-jeton-API" target=_blank>'.
                                 $this->l('Click here') .'</a><br/>'.
                                 $this->l('Copy paste the generated token in the field below'),
                'input' => array(
                    array(
                        'type'     => 'text',
                        'label'    => $this->l('Slack Token'),
                        'name'     => 'SLACK_TOKEN',
                        'required' => true
                        ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'saveConfigurationToken'
                    )
                ),
        );

        $fields_form_2 = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Customer notifications'),
                    'icon'  => 'icon-envelope'
                ),

              'input'   => array(
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Customer notifications'),
                        'name'    => 'SLACK_NEW_CUSTOMER',
                        'is_bool' => true,
                        'desc'    => $this->l('Activate notifications for customers add.'),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                                ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                                )
                            ),
                    ),
                    array(
                        'type'     => 'select',
                        'label'    => $this->l('Channel'),
                        'name'     => 'SLACK_CHANNEL_CUSTOMER',
                        'desc'     => $this->l('Please complete the token input for select the channel.'),
                        'options'  => array(
                            'query' => $options,
                            'id'    => 'id_option',
                            'name'  => 'name',
                            )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'saveConfigurationCustomer'
                    )
            ),
        );

        $fields_form_3 = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Order notifications'),
                    'icon'  => 'icon-envelope'
                ),

                'input'   => array(
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Order notifications'),
                        'name'    => 'SLACK_NEW_ORDER',
                        'is_bool' => true,
                        'desc'    => $this->l('Activate notification for each new order.'),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                                ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                                )
                            ),
                    ),
                    array(
                        'type'     => 'select',
                        'label'    => $this->l('Channel'),
                        'name'     => 'SLACK_CHANNEL_ORDER',
                        'desc'     => $this->l('Please complete the token input for select the channel.'),
                        'options'  => array(
                            'query' => $options,
                            'id'    => 'id_option',
                            'name'  => 'name',
                            )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'saveConfigurationOrder'
                    )
            ),
        );

        $fields_form_4 = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Out of stock notifications'),
                    'icon'  => 'icon-envelope'
                ),

              'input'   => array(
                    array(
                        'type'    => 'switch',
                        'label'   => $this->l('Out of stock notifications'),
                        'name'    => 'SLACK_OUT_OF_STOCK',
                        'is_bool' => true,
                        'desc'    => $this->l('Activate notification for a new out of stock product.'),
                        'values'  => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                                ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                                )
                            ),
                    ),
                    array(
                        'type'     => 'select',
                        'label'    => $this->l('Channel'),
                        'name'     => 'SLACK_CHANNEL_STOCK',
                        'desc'     => $this->l('Please complete the token input for select the channel.'),
                        'options'  => array(
                            'query' => $options,
                            'id'    => 'id_option',
                            'name'  => 'name',
                            )
                    ),
                     array(
                        'type' => 'text',
                        'label' => $this->l('Quantity out of stock'),
                        'name' => 'SLACK_QTIES_OUT',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->l('Quantity for which a product is considered out of stock.'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                    'name'  => 'saveConfigurationStock'
                    )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
                                  ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'
        &configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
            );

        return $helper->generateForm(array($fields_form_1, $fields_form_2, $fields_form_3, $fields_form_4));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'SLACK_TOKEN'           => Tools::getValue('SLACK_TOKEN', Configuration::get('SLACK_TOKEN')),
            'SLACK_CHANNEL_CUSTOMER'=> Tools::getValue('SLACK_CHANNEL_CUSTOMER', Configuration::get('SLACK_CHANNEL_CUSTOMER')),
            'SLACK_CHANNEL_ORDER'   => Tools::getValue('SLACK_CHANNEL_ORDER', Configuration::get('SLACK_CHANNEL_ORDER')),
            'SLACK_CHANNEL_STOCK'   => Tools::getValue('SLACK_CHANNEL_STOCK', Configuration::get('SLACK_CHANNEL_STOCK')),
            'SLACK_NEW_CUSTOMER'    => Tools::getValue('SLACK_NEW_CUSTOMER', Configuration::get('SLACK_NEW_CUSTOMER')),
            'SLACK_NEW_ORDER'       => Tools::getValue('SLACK_NEW_ORDER', Configuration::get('SLACK_NEW_ORDER')),
            'SLACK_OUT_OF_STOCK'    => Tools::getValue('SLACK_OUT_OF_STOCK', Configuration::get('SLACK_OUT_OF_STOCK')),
            'SLACK_QTIES_OUT'       => Tools::getValue('SLACK_QTIES_OUT', Configuration::get('SLACK_QTIES_OUT')),
            );
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $token = Tools::getValue('SLACK_TOKEN');

            if (!empty($token)) {
                $validation = slackAPI::checkTokenValidity($token);
            }

            if (!$token) {
                $this->_postErrors[] = $this->l('Token field is required');
            } elseif (!$validation->ok) {
                $this->_postErrors[] = $this->l('This token is not valid');
            } elseif ($validation->ok) {
                $channels = slackAPI::getChannelsListByToken($token, true);
            }

            if (Tools::getValue('SLACK_NEW_CUSTOMER') &&
                Tools::getValue('SLACK_CHANNEL_CUSTOMER') == 'undefined') {
                $_POST['SLACK_NEW_CUSTOMER'] = 0;
                $this->_postErrors[] = $this->l('You must define a channel to receive your customer notifications');
            }

            if (Tools::getValue('SLACK_NEW_ORDER') &&
                Tools::getValue('SLACK_CHANNEL_ORDER') == 'undefined') {
                $_POST['SLACK_NEW_ORDER'] = 0;
                $this->_postErrors[] = $this->l('You must define a channel to receive your order notifications');
            }

            if (Tools::getValue('SLACK_OUT_OF_STOCK') &&
                !Validate::isInt(Tools::getValue('SLACK_QTIES_OUT'))) {
                $_POST['SLACK_OUT_OF_STOCK'] = 0;
                $_POST['SLACK_CHANNEL_STOCK'] = 'undefined';
                $this->_postErrors[] = $this->l('The quantities is not an integer');
            }
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('SLACK_TOKEN', Tools::getValue('SLACK_TOKEN'));
            Configuration::updateValue('SLACK_CHANNEL_CUSTOMER', Tools::getValue('SLACK_CHANNEL_CUSTOMER'));
            Configuration::updateValue('SLACK_CHANNEL_ORDER', Tools::getValue('SLACK_CHANNEL_ORDER'));
            Configuration::updateValue('SLACK_CHANNEL_STOCK', Tools::getValue('SLACK_CHANNEL_STOCK'));
            Configuration::updateValue('SLACK_NEW_CUSTOMER', Tools::getValue('SLACK_NEW_CUSTOMER'));
            Configuration::updateValue('SLACK_NEW_ORDER', Tools::getValue('SLACK_NEW_ORDER'));
            Configuration::updateValue('SLACK_OUT_OF_STOCK', Tools::getValue('SLACK_OUT_OF_STOCK'));
            Configuration::updateValue('SLACK_QTIES_OUT', Tools::getValue('SLACK_QTIES_OUT'));
        }
        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    public function hookActionValidateOrder($params)
    {
        if (Configuration::get('SLACK_NEW_ORDER')
            && Configuration::get('SLACK_CHANNEL_ORDER')
            && Configuration::get('SLACK_TOKEN')) {
            $message  = $this->l('A new order by ');
            $message .= $params['order']->payment;
            $message .= $this->l(' of *');
            $message .= $params['order']->total_paid;
            $message .= $this->l(' €* WT has just been registered for ');
            $message .= $params['customer']->firstname.' '.$params['customer']->lastname;

            $parameters = array(
            'token' => Configuration::get('SLACK_TOKEN'),
            'channel' => Configuration::get('SLACK_CHANNEL_ORDER'),
            'text' => $message,
            'icon_emoji' => ':package:',
            'username' => Configuration::get('PS_SHOP_NAME')
            );

            SlackAPI::curlCall('chat.postMessage', $parameters);
        }
    }

    public function hookactionObjectCustomerAddAfter($params)
    {
        if (Configuration::get('SLACK_NEW_CUSTOMER')
            && Configuration::get('SLACK_TOKEN')
            && Configuration::get('SLACK_CHANNEL_CUSTOMER')) {
            $message  = $this->l('New customer registration : ');
            $message .= $params['object']->firstname." ".$params['object']->lastname;
            $message .= $this->l(' - mail : ');
            $message .= $params['object']->email;

            if ($params['object']->id_gender == 2) {
                $emoji = ':woman:';
            } else {
                $emoji = ':man:';
            }

            $parameters = array(
                'token' => Configuration::get('SLACK_TOKEN'),
                'channel' => Configuration::get('SLACK_CHANNEL_CUSTOMER'),
                'text' => $message,
                'icon_emoji' => $emoji,
                'username' => Configuration::get('PS_SHOP_NAME')
            );

            SlackAPI::curlCall('chat.postMessage', $parameters);
        }
    }

    public function hookActionUpdateQuantity($params)
    {
        if (!empty(Configuration::get('SLACK_TOKEN') &&
            Configuration::get('SLACK_CHANNEL_STOCK') != "undefined")) {
            $id_product = (int)$params['id_product'];
            $id_product_attribute = (int)$params['id_product_attribute'];

            $quantity = (int)$params['quantity'];
            $context = Context::getContext();
            $id_shop = (int)$context->shop->id;
            $id_lang = (int)$context->language->id;
            $product = new Product($id_product, false, $id_lang, $id_shop, $context);
            $product_has_attributes = $product->hasAttributes();
            $configuration = Configuration::getMultiple(
                array(
                    'SLACK_QTIES_OUT',
                    'PS_STOCK_MANAGEMENT'
                ), null, null, $id_shop
            );
            $ma_last_qties = (int)$configuration['SLACK_QTIES_OUT'];

            $check_oos = ($product_has_attributes && $id_product_attribute) || (!$product_has_attributes && !$id_product_attribute);

            if ($check_oos &&
                $product->active == 1 &&
                (int)$quantity <= $ma_last_qties &&
                $configuration['PS_STOCK_MANAGEMENT']) {
                $iso = Language::getIsoById($id_lang);
                $product_name = Product::getProductName($id_product, $id_product_attribute, $id_lang);

                // Do not send message if multiples product are created / imported.
                if (!defined('PS_MASS_PRODUCT_CREATION')) {
                        $message = $this->l('Product out of stock : ```');
                        $message .= $product_name .' ( id_product '. $id_product .' ) ';
                        $message .= $this->l(' current quantities : ');
                        $message .= $quantity;
                        $message .= $this->l('```');
                        $parameters = array(
                        'token' => Configuration::get('SLACK_TOKEN'),
                        'channel' => Configuration::get('SLACK_CHANNEL_STOCK'),
                        'text' => $message,
                        'icon_emoji' => ':warning:',
                        'username' => Configuration::get('PS_SHOP_NAME')
                        );
                    SlackAPI::curlCall('chat.postMessage', $parameters);
                }
            }
        }
    }
}
