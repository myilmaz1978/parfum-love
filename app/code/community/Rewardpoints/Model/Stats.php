<?php
/**
 * J2T RewardsPoint2
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@j2t-design.com so we can send you a copy immediately.
 *
 * @category   Magento extension
 * @package    RewardsPoint2
 * @copyright  Copyright (c) 2009 J2T DESIGN. (http://www.j2t-design.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Rewardpoints_Model_Stats extends Mage_Core_Model_Abstract
{
    const TARGET_PER_ORDER     = 1;
    const TARGET_FREE   = 2;
    const APPLY_ALL_ORDERS  = '-1';

    const TYPE_POINTS_ADMIN  = '-1';
    const TYPE_POINTS_REVIEW  = '-2';
    const TYPE_POINTS_REGISTRATION  = '-3';
    const TYPE_POINTS_REQUIRED  = '-10';
    const TYPE_POINTS_BIRTHDAY  = '-20';
    const TYPE_POINTS_FB  = '-30';
    const TYPE_POINTS_GP  = '-40';
    const TYPE_POINTS_PIN  = '-50';
    const TYPE_POINTS_TT  = '-60';
    
    const TYPE_POINTS_NEWSLETTER  = '-70';

    protected $_targets;

    protected $_eventPrefix = 'rewardpoints_account';
    protected $_eventObject = 'stats';

    protected $points_received;
    protected $points_received_no_exp;
    // J2T points validation date
    protected $points_received_reajust;
    protected $points_spent;
    
    protected $points_lost;
    protected $points_waiting;
    
    const XML_PATH_NOTIFICATION_EMAIL_TEMPLATE       = 'rewardpoints/notifications/notification_email_template';
    const XML_PATH_NOTIFICATION_EMAIL_IDENTITY       = 'rewardpoints/notifications/notification_email_identity';
    
    const XML_PATH_NOTIFICATION_ADMIN_EMAIL_TEMPLATE       = 'rewardpoints/notifications/notification_admin_email_template';
    const XML_PATH_NOTIFICATION_ADMIN_EMAIL_IDENTITY       = 'rewardpoints/notifications/notification_admin_email_identity';

    public function _construct()
    {
        parent::_construct();
        $this->_init('rewardpoints/stats');

        $this->_targets = array(
            self::TARGET_PER_ORDER     => Mage::helper('rewardpoints')->__('Related to Order ID'),
            self::TARGET_FREE   => Mage::helper('rewardpoints')->__('Not related to Order ID'),
        );
    }
    
    public function constructSqlPointsType($table_prefix){
        $arr_sql = array();
        foreach ($this->getPointsDefaultTypeToArray() as $key => $value){
            $arr_sql[] = $table_prefix.".order_id = '".$key."' ";
            //Mage::getSingleton('core/resource')->getTableName('rewardpoints_account').".order_id = '".Rewardpoints_Model_Stats::TYPE_POINTS_REVIEW."' 
            //or ".Mage::getSingleton('core/resource')->getTableName('rewardpoints_account').".order_id = '".Rewardpoints_Model_Stats::TYPE_POINTS_REGISTRATION."'
        }
        return implode(" or ", $arr_sql);
    }
    
    public function getPointsDefaultTypeToArray(){
        $return_value = array(self::TYPE_POINTS_FB => Mage::helper('rewardpoints')->__('Facebook Like points'), //OK
            self::TYPE_POINTS_PIN => Mage::helper('rewardpoints')->__('Pinterest points'), //OK
            self::TYPE_POINTS_TT => Mage::helper('rewardpoints')->__('Twitter points'), //OK
            self::TYPE_POINTS_GP => Mage::helper('rewardpoints')->__('Google Plus points'), //OK
            self::TYPE_POINTS_BIRTHDAY => Mage::helper('rewardpoints')->__('Birthday points'), //OK
            self::TYPE_POINTS_REVIEW => Mage::helper('rewardpoints')->__('Review points'), //OK
            self::TYPE_POINTS_NEWSLETTER => Mage::helper('rewardpoints')->__('Newsletter points'), //OK
            self::TYPE_POINTS_ADMIN => Mage::helper('rewardpoints')->__('Admin gift'), //OK
            self::TYPE_POINTS_REGISTRATION => Mage::helper('rewardpoints')->__('Registration points')); //OK
        
        if (Mage::getConfig()->getModuleConfig('J2t_Rewardshare')->is('active', 'true')){
            $return_value[J2t_Rewardshare_Model_Stats::TYPE_POINTS_SHARE] = Mage::helper('j2trewardshare')->__('Gift (shared points)');
        }
        
        return $return_value;
        //J2t_Rewardshare_Model_Stats::TYPE_POINTS_SHARE => Mage::helper('j2trewardshare')->__('Gift (shared points)')
        
    }
    
    public function getPointsTypeToArray(){
        $return_value = array(self::TYPE_POINTS_FB => Mage::helper('rewardpoints')->__('Facebook Like points'), //OK
            self::TYPE_POINTS_GP => Mage::helper('rewardpoints')->__('Google Plus points'), //OK
            self::TYPE_POINTS_PIN => Mage::helper('rewardpoints')->__('Pinterest points'), //OK
            self::TYPE_POINTS_TT => Mage::helper('rewardpoints')->__('Twitter points'), //OK
            self::TYPE_POINTS_BIRTHDAY => Mage::helper('rewardpoints')->__('Birthday points'), //OK
            self::TYPE_POINTS_REVIEW => Mage::helper('rewardpoints')->__('Review points'), //OK
            self::TYPE_POINTS_NEWSLETTER => Mage::helper('rewardpoints')->__('Newsletter points'), //OK
            self::TYPE_POINTS_ADMIN => Mage::helper('rewardpoints')->__('Admin gift'), //OK
            self::TYPE_POINTS_REQUIRED => Mage::helper('rewardpoints')->__('Points used on products'), 
            self::TYPE_POINTS_REGISTRATION => Mage::helper('rewardpoints')->__('Registration points')); //OK
        
        if (Mage::getConfig()->getModuleConfig('J2t_Rewardshare')->is('active', 'true')){
            $return_value[J2t_Rewardshare_Model_Stats::TYPE_POINTS_SHARE] = Mage::helper('j2trewardshare')->__('Gift (shared points)');
        }
        
        return $return_value;
    }

    public function getTargetsArray()
    {
        return $this->_targets;
    }

    public function targetsToOptionArray()
    {
        return $this->_toOptionArray($this->_targets);
    }

    protected function _toOptionArray($array)
    {
        $res = array();
        foreach ($array as $value => $label) {
        	$res[] = array('value' => $value, 'label' => $label);
        }
        return $res;
    }
    
    
    //J2T Check referral
    public function loadByReferralId($referral_id, $referral_customer_id = null)
    {
        $this->addData($this->getResource()->loadByReferralId($referral_id, $referral_customer_id));
        return $this;
    }
    
    
    public function loadByChildReferralId($referral_id, $referral_customer_id = null)
    {
        $this->addData($this->getResource()->loadByChildReferralId($referral_id, $referral_customer_id));
        return $this;
    }
    
    public function loadByOrderIncrementId($order_id, $customer_id = null, $referral = false, $parent = false)
    {
        $this->addData($this->getResource()->loadByOrderIncrementId($order_id, $customer_id, $referral, $parent));
        return $this;
    }
    
    
    public function loadpointsbydate($store_id, $customer_id, $date){
        $collection = $this->getCollection();
        $collection->getSelect()->where("main_table.customer_id = ?", $customer_id);        
        $collection->getSelect()->where("( ? >= main_table.date_start )", $date);
        $collection->getSelect()->where("( main_table.date_end >= ? )", $date);
        $collection->getSelect()->where("( main_table.date_end <= NOW() )");
        $collection->addValidPoints($store_id, true);
        
        //echo $collection->getSelect()->__toString();
        //die;

        $row = $collection->getFirstItem();
        if (!$row) return $this;
        return $row;
    }
    
    public function getDobPoints($store_id, $customer_id) 
    {
        //self::TYPE_POINTS_BIRTHDAY
        $collection = $this->getCollection();
        $collection->getSelect()->where("main_table.customer_id = ?", $customer_id);        
        //$collection->getSelect()->where("( ? >= main_table.date_start )", $date);
        $collection->getSelect()->where("main_table.order_id  = ?", self::TYPE_POINTS_BIRTHDAY);
        $collection->pointsByDate();
        
        $row = $collection->getFirstItem();
        if (!$row) return $this;
        return $row;
    }

    public function loadByCustomerId($customer_id)
    {
        $collection = $this->getCollection();
        $collection->getSelect()->where('customer_id = ?', $customer_id);

        $row = $collection->getFirstItem();
        if (!$row) return $this;
        return $row;
    }
    
    public function loadReferrer($customer_id, $order_id)
    {
        $collection = $this->getCollection();
        $collection->getSelect()->where('customer_id <> ?', $customer_id);
        $collection->getSelect()->where('order_id = ?', $order_id);
        
        
        $row = $collection->getFirstItem();
        if (!$row) return $this;
        return $row;
    }

    public function checkProcessedOrder($customer_id, $order_id, $isCredit = true, $link_id = false, $exclude_referral = true)
    {
        $collection = $this->getCollection();
        $collection->getSelect()->where('customer_id = ?', $customer_id);
        $collection->getSelect()->where('order_id = ?', $order_id);
        if ($link_id){
            $collection->getSelect()->where('rewardpoints_linker = ?', $link_id);
        }
        
        if ($isCredit){
            $collection->getSelect()->where('points_current > 0');
        } else {
            $collection->getSelect()->where('points_spent > 0');
        }
        
        if ($exclude_referral){
            $collection->getSelect()->where('rewardpoints_referral_id IS NULL');
        }

        $row = $collection->getFirstItem();
        if (!$row) return $this;
        return $row;
    }


    public function getPointsUsed($order_id, $customer_id)
    {
        $collection = $this->getCollection();
        $collection->getSelect()->where('customer_id = ?', $customer_id);
        $collection->getSelect()->where('order_id = ?', $order_id);
        $collection->getSelect()->where('points_spent > ?', '0');

        $row = $collection->getFirstItem();
        if (!$row) return $this;
        return $row;
    }


    /*public function getPointsWaitingValidation($customer_id, $store_id){
        $collection = $this->getCollection()->joinFullCustomerPoints($customer_id, $store_id);
        $row = $collection->getFirstItem();
        return $row->getNbCredit() - $this->getPointsReceived($customer_id, $store_id) + $this->getPointsReceivedReajustment($customer_id, $store_id);
    }*/
    
    public function getPointsWaitingValidation($customer_id, $store_id){
        
        if ($this->points_waiting != null){
            return $this->points_waiting;
        } else {
            $collection = $this->getCollection();
            $collection->joinValidPointsOrder($customer_id, $store_id, array("new"), false, true);
            
            foreach($this->getPointsTypeToArray() as $point_type => $point_text){
                $collection->getSelect()->where("main_table.order_id <> '$point_type'");
            }
            
            
            $row = $collection->getFirstItem();
            $this->points_waiting = $row->getNbCredit();
            //$this->points_waiting = $row->getNbCredit() - $this->getRealPointsReceivedNoExpiry($customer_id, $store_id);
            return $this->points_waiting;
        }
        
        /*return $this->getRealPointsReceivedNoExpiry($customer_id, $store_id) 
                - $this->getPointsSpent($customer_id, $store_id) 
                - $this->getPointsCurrent($customer_id, $store_id) 
                - $this->getRealPointsLost($customer_id, $store_id);*/
        /*$collection = $this->getCollection()->joinFullCustomerPoints($customer_id, $store_id);
        $row = $collection->getFirstItem();
        return $row->getNbCredit() - $this->getPointsReceived($customer_id, $store_id) + $this->getPointsReceivedReajustment($customer_id, $store_id);*/
    }
    
    public function sendAdminNotification(Mage_Customer_Model_Customer $customer, $store_id, $points, $description)
    {
        $translate = Mage::getSingleton('core/translate');
        /* @var $translate Mage_Core_Model_Translate */
        $translate->setTranslateInline(false);

        $email = Mage::getModel('core/email_template');

        $template = Mage::getStoreConfig(self::XML_PATH_NOTIFICATION_ADMIN_EMAIL_TEMPLATE, $store_id);
        $recipient = array(
            'email' => $customer->getEmail(),
            'name'  => $customer->getName()
        );

        $sender  = Mage::getStoreConfig(self::XML_PATH_NOTIFICATION_ADMIN_EMAIL_IDENTITY, $store_id);
        $email->setDesignConfig(array('area'=>'frontend', 'store'=>$store_id))
                ->sendTransactional(
                    $template,
                    $sender,
                    $recipient['email'],
                    $recipient['name'],
                    array(
                        'points'   => $points,
                        'description'   => $description,
                        'customer' => $customer,
                        'rewardpoint'  => $this
                    )
                );
        $translate->setTranslateInline(true);
        return $email->getSentSuccess();
    }
    
    
    public function sendNotification(Mage_Customer_Model_Customer $customer, $store_id, $points, $days)
    {
        $translate = Mage::getSingleton('core/translate');
        /* @var $translate Mage_Core_Model_Translate */
        $translate->setTranslateInline(false);

        $email = Mage::getModel('core/email_template');

        $template = Mage::getStoreConfig(self::XML_PATH_NOTIFICATION_EMAIL_TEMPLATE, $store_id);
        $recipient = array(
            'email' => $customer->getEmail(),
            'name'  => $customer->getName()
        );

        $sender  = Mage::getStoreConfig(self::XML_PATH_NOTIFICATION_EMAIL_IDENTITY, $store_id);
        $email->setDesignConfig(array('area'=>'frontend', 'store'=>$store_id))
                ->sendTransactional(
                    $template,
                    $sender,
                    $recipient['email'],
                    $recipient['name'],
                    array(
                        'points'   => $points,
                        'days'   => $days,
                        'customer' => $customer
                    )
                );
        $translate->setTranslateInline(true);
        return $email->getSentSuccess();
    }
    
    
    
    
    /*
     * FIX - expiry dates of points
     */
    public function loadallpointsbydate($customer_id, $store_id, $date_end = null){
        $collection = $this->getCollection();
        $collection->getSelect()->where("main_table.customer_id = ?", $customer_id);
        if ($date_end){
            $collection->getSelect()->where("( ? <= main_table.date_end )", $date_end);
        }
        /*if ($last_date){
            $collection->getSelect()->where("( ? <= main_table.date_start )", $last_date);
        }*/
        
        //NEED to get all valid points collected
        /*$collection->getSelect()->where("( main_table.date_start <= NOW() )");
        $collection->getSelect()->where("( main_table.date_start IS NOT NULL )");
        $collection->getSelect()->where("( main_table.date_end IS NOT NULL )");*/
        //VERIFY THIS
        $collection->getSelect()->where("( main_table.date_start IS NOT NULL OR main_table.date_end IS NOT NULL )");
        
        $collection->addValidPoints($store_id, true, true);
        
        
        $collection->setOrder('date_start ',  'ASC');
        $collection->setOrder('points_current ',  'DESC');
        $collection->setOrder('date_end ',  'ASC');
        
        /*echo $collection->getSelect()->__toString();
        die;*/
        
        return $collection->load();
    }
    
    protected function getPointsReceivedReajustment($customer_id, $store_id) {
        $acc_fix_points = 0;
        
        if ($this->points_received_reajust != null){
            return $this->points_received_reajust;
        } else {
            //1. get all points gathered for this user (valid points) and put in array(points_backup, points_calc, date_from, date_end, store_id)
            //2. get all used points
            $points = $this
                        ->getResourceCollection()
                        ->addUsedpointsbydate($store_id, $customer_id);
            
            
            $valid_points = $this->loadallpointsbydate($customer_id, $store_id);
            
            $arr_points_collection = array();
            if ($valid_points->count()){
                foreach ($valid_points as $valid_point){
                    $arr_points_collection[] = array("points" => $valid_point->getData('points_current'), 
                        "points_calculated" => $valid_point->getData('points_current'),
                        //"date_from" => $valid_point->getData('date_start'),
                        //"date_end" => $valid_point->getData('date_end')
                        "date_from" => ($valid_point->getData('date_start')) ? $valid_point->getData('date_start') : date('Y-m-d', mktime(0, 0, 0, 1, 1, 1970)),
                        "date_end" => ($valid_point->getData('date_end')) ? $valid_point->getData('date_end') : date('Y-m-d', mktime(0, 0, 0, 1, 1, date("Y")+1))
                            );
                }
            }
            
            $today_stamp = Mage::getModel('core/date')->timestamp(time());
            
            $acc_fix_points = 0;
            $last_date = null;
            if ($points->getSize() && sizeof($arr_points_collection)){
                $left_over = 0;
                
                $extra = 0;
                foreach ($points as $current_point){
                    $date_used = $current_point->getData('date_order');
                    $date_used_iso = new Zend_Date($date_used, Zend_Date::ISO_8601);
                    $date_used_stamp = $date_used_iso->getTimestamp();
                    
                    $checked = false;
                    //$extra = 0;
                    foreach($arr_points_collection as $key => $point_collection_value){
                        
                        $date_from = $point_collection_value['date_from'];
                        $date_from_iso = new Zend_Date($date_from, Zend_Date::ISO_8601);
                        $date_from_stamp = $date_from_iso->getTimestamp();
                        
                        $date_end = $point_collection_value['date_end'];
                        $date_end_iso = new Zend_Date($date_end, Zend_Date::ISO_8601);
                        $date_end_stamp = $date_end_iso->getTimestamp();
                        
                        if ($date_from_stamp <= $date_used_stamp && $date_end_stamp >= $date_used_stamp && $today_stamp > $date_end_stamp && !$checked && $arr_points_collection[$key]['points_calculated'] > 0){
                        //if ($date_from_stamp <= $date_used_stamp && $date_end_stamp >= $date_used_stamp && $today_stamp > $date_end_stamp && !$checked && $arr_points_collection[$key]['points_calculated'] > 0){
                            $calc = $arr_points_collection[$key]['points_calculated'] - $current_point->getData('nb_credit_spent')+$extra;
                            $arr_points_collection[$key]['points_calculated'] = ($calc > 0) ? $calc : 0;
                            
                            if ($calc < 0){
                                $extra = $calc;
                                $checked = false;
                            } else {
                                $extra = 0;
                                $checked = true;
                            }
                        } else if ($today_stamp <= $date_end_stamp) {
                            $arr_points_collection[$key]['points_calculated'] = 0;
                        }
                    }
                }
                
                
                foreach($arr_points_collection as $key_p => $point_collection_value){
                    $acc_fix_points -= $point_collection_value['points_calculated'];
                    $arr_points_collection[$key_p]['points_calculated'] = 0;
                }
                
            }
            
            //flush any residual expired points
            if (sizeof($arr_points_collection)){
                foreach($arr_points_collection as $key => $point_collection_value){
                    
                    $date_from = $point_collection_value['date_from'];
                    $date_from_iso = new Zend_Date($date_from, Zend_Date::ISO_8601);
                    $date_from_stamp = $date_from_iso->getTimestamp();
                    
                    $date_end = $point_collection_value['date_end'];
                    $date_end_iso = new Zend_Date($date_end, Zend_Date::ISO_8601);
                    $date_end_stamp = $date_end_iso->getTimestamp();
                    
                    if ($today_stamp > $date_end_stamp || $today_stamp < $date_from_stamp){
                        $acc_fix_points -= $point_collection_value['points_calculated'];
                    }
                }
            }
        }
        
        
        $this->points_received_reajust = $acc_fix_points;
        return $acc_fix_points;
    }
    
    /**
     * J2T modification fixing issue related to points validation dates
     * getPointsReceivedReajustment protected function allowing to readjust points regarding points validation dates
     * @param int $customer_id
     * @param int $store_id
     * @return int 
     */
    protected function _getPointsReceivedReajustment($customer_id, $store_id) {
        /*$points = Mage::getModel('rewardpoints/stats')
                                ->getResourceCollection()
                                ->addUsedpointsbydate($store_id, $customer_id);*/
        
        if ($this->points_received_reajust != null){
            return $this->points_received_reajust;
        } else {
            //get all points used groupped by date
            $points = $this
                                ->getResourceCollection()
                                ->addUsedpointsbydate($store_id, $customer_id);
            $acc_fix_points = 0;
            if ($points->getSize()){
                foreach ($points as $current_point){
                    //validate points per date
                    $points_accum = Mage::getModel('rewardpoints/stats')->loadpointsbydate($store_id, $customer_id, $current_point->getData('date_order'));
                    //if ($points_accum->getData('nb_credit') >= $current_point->getData('nb_credit_spent')){
                    //FIX POINTS READJUST!!!!
                    if ($points_accum->getData('nb_credit') >= $current_point->getData('nb_credit_spent')){
                        $acc_fix_points += $current_point->getData('nb_credit_spent');
                    } 
                }
            }
            $this->points_received_reajust = $acc_fix_points;
            return $acc_fix_points;
        }        
    }
    
    
    public function getRealPointsLost($customerId, $store_id) {
        if ($this->points_lost){
            return $this->points_lost;
        }
        $this->points_lost = $this->getRealPointsReceivedNoExpiry($customerId, $store_id) - $this->getPointsReceived($customerId, $store_id);
        return $this->points_lost;
    }


    public function getPointsReceived($customer_id, $store_id){
        if ($this->points_received){
            return $this->points_received;
        }
        $statuses = Mage::getStoreConfig('rewardpoints/default/valid_statuses', Mage::app()->getStore()->getId());
        $order_states = explode(",", $statuses);

        //$order_states = array("'processing'","'complete'");
        $collection = $this->getCollection();
        //$collection->joinValidPointsOrder($customer_id, $store_id, $order_states);
        //FIX J2T - exp date
        $collection->joinValidPointsOrder($customer_id, $store_id, $order_states, false, true);
        
        /*$collection->printlogquery(true);
        die;*/
        $row = $collection->getFirstItem();
        
        //echo $this->getPointsReceivedReajustment($customer_id, $store_id);
        //die;
        
        $this->points_received = $row->getNbCredit() + $this->getPointsReceivedReajustment($customer_id, $store_id);
        
        //J2T modification fixing issue related to points validation dates
        //return $row->getNbCredit();
        //echo $collection->getSelect()->__toString();
        //die;
        return $row->getNbCredit() + $this->getPointsReceivedReajustment($customer_id, $store_id);
    }
    
    
    
    public function getRealPointsReceivedNoExpiry($customer_id, $store_id){
        if ($this->points_received_no_exp){
            return $this->points_received_no_exp;
        }
        $statuses = Mage::getStoreConfig('rewardpoints/default/valid_statuses', Mage::app()->getStore()->getId());
        $order_states = explode(",", $statuses);

        //$order_states = array("'processing'","'complete'");
        $collection = $this->getCollection();
        $collection->joinValidPointsOrder($customer_id, $store_id, $order_states, false, true);
        
        /*$collection->printlogquery(true);
        die;*/
        $row = $collection->getFirstItem();
        $this->points_received_no_exp = $row->getNbCredit();
        
        //J2T modification fixing issue related to points validation dates
        //return $row->getNbCredit();
        //echo $collection->getSelect()->__toString();
        //die;
        return $row->getNbCredit();
    }

    public function getPointsSpent($customer_id, $store_id){
        
        if ($this->points_spent){
            return $this->points_spent;
        }

        $statuses = Mage::getStoreConfig('rewardpoints/default/valid_statuses', Mage::app()->getStore()->getId());
        $order_states = explode(",", $statuses);
        $order_states[] = 'new';


        //$order_states = array("'processing'","'complete'","'new'");

        $collection = $this->getCollection();
        $collection->joinValidPointsOrder($customer_id, $store_id, $order_states, true);
        
        $row = $collection->getFirstItem();

        $this->points_spent = $row->getNbCredit();

        return $row->getNbCredit();
    }

    public function getPointsCurrent($customer_id, $store_id){
        $total = $this->getPointsReceived($customer_id, $store_id) - $this->getPointsSpent($customer_id, $store_id);
        if ($total > 0){
                return $total;
        } else {
                return 0;
        }
    }

    public function recordPoints($pointsInt, $customerId, $orderId, $store_id, $force_nodelay = false) {
        $post = array(
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'store_id' => $store_id,
            'points_current' => $pointsInt,
            'convertion_rate' => Mage::getStoreConfig('rewardpoints/default/points_money', $store_id)
            );
        //v.2.0.0
        $add_delay = 0;
        if ($delay = Mage::getStoreConfig('rewardpoints/default/points_delay', $store_id) && $force_nodelay){
            if (is_numeric($delay)){
                $post['date_start'] = $this->getResource()->formatDate(mktime(0, 0, 0, date("m"), date("d")+$delay, date("Y")));
                $add_delay = $delay;
            }
        }

        if ($duration = Mage::getStoreConfig('rewardpoints/default/points_duration', $store_id)){
            if (is_numeric($duration)){
                if (!isset($post['date_start'])){
                    $post['date_start'] = $this->getResource()->formatDate(time());
                }
                $post['date_end'] = $this->getResource()->formatDate(mktime(0, 0, 0, date("m"), date("d")+$duration+$add_delay, date("Y")));
            }
        }
        $this->setData($post);
        $this->save();
    }

    public function _afterSave()
    {
        if ($customer_id = $this->getCustomerId()){
            $allStores = Mage::app()->getStores();
            foreach ($allStores as $_eachStoreId => $val) {
                //Mage::helper('rewardpoints')->processRecordFlatAction($customer_id, Mage::app()->getStore($_eachStoreId)->getId());
                $model = Mage::getModel('rewardpoints/flatstats');
                $model->processRecordFlat($customer_id, Mage::app()->getStore($_eachStoreId)->getId(), false, true);
            }
        }
        return parent::_afterSave();
        //return $this;
    }
    
    public function _afterDelete()
    {
        if ($customer_id = $this->getCustomerId()){
            $allStores = Mage::app()->getStores();
            foreach ($allStores as $_eachStoreId => $val) {
                //Mage::helper('rewardpoints')->processRecordFlatAction($customer_id, Mage::app()->getStore($_eachStoreId)->getId());
                $model = Mage::getModel('rewardpoints/flatstats');
                $model->processRecordFlat($customer_id, Mage::app()->getStore($_eachStoreId)->getId(), false, true);
            }
        }
        return parent::_afterDelete();
        //return $this;
    }

}

