<?php

require_once 'CRM/Core/Page.php';
require_once 'CRM/Banking/Helpers/OptionValue.php';

class CRM_Banking_Page_Dashboard extends CRM_Core_Page {
  function run() {
    CRM_Utils_System::setTitle(ts('CiviBanking Dashboard'));


    $now = strtotime("now");
    $week_count = 7;
    $oldest_week = date('YW', strtotime("now -$week_count weeks"));
    $payment_states = banking_helper_optiongroup_id_name_mapping('civicrm_banking.bank_tx_status');
    $account_names = array();

    // get the week based data
    $account_week_data = array();
	for ($i=$week_count; $i>=0; $i--)
		$weeks[] = date('YW', strtotime("now -$i weeks"));
    $account_based_data_sql = "
    SELECT
      COUNT(*)             AS count,
      YEARWEEK(value_date) AS year_week,
      ba_id                AS bank_account_id,
      status_id            AS status_id
    FROM
      civicrm_bank_tx
    GROUP BY
      ba_id, year_week, status_id;
    ";
    $results = CRM_Core_DAO::executeQuery($account_based_data_sql);
    while ($results->fetch()) {
    	// get the account
    	if (empty($results->bank_account_id)) {
    		$account_id = 0;
    	} else {
    		$account_id = $results->bank_account_id;
    	}
    	if (!isset($account_week_data[$account_id])) $account_week_data[$account_id] = array();

    	// get the week
    	$week = $results->year_week;
    	if ($week < $oldest_week) $week = 'before';
		if (!isset($account_week_data[$account_id][$week])) $account_week_data[$account_id][$week] = array();

		// get the status
		$status_id = $results->status_id;
		if ($results->status_id==$payment_states['processed']['id'] || $results->status_id==$payment_states['ignored']['id']) {
			if (!isset($account_week_data[$account_id][$week]['done'])) $account_week_data[$account_id][$week]['done'] = 0;
			$account_week_data[$account_id][$week]['done'] += $results->count;
		}
		//if (!isset($account_week_data[$account_id][$week][$status_id])) $account_week_data[$account_id][$week][$status_id] = 0;
		//$account_week_data[$account_id][$week][$status_id] += 1;
		if (!isset($account_week_data[$account_id][$week]['sum'])) $account_week_data[$account_id][$week]['sum'] = 0;
		$account_week_data[$account_id][$week]['sum'] += $results->count;
    }

    // fill empty weeks
    foreach ($account_week_data as $account_id => $account_data) {
    	for ($i=$week_count; $i>=0; $i--) { 
    		$week = date('YW', strtotime("now -$i weeks"));
    		if (!isset($account_data[$week])) {
    			$account_week_data[$account_id][$week] = array('sum' => 0);
    		}
    	}
		if (!isset($account_data['before'])) {
			$account_week_data[$account_id]['before'] = array('sum' => 0);
		}

		// look up account name
		if ($account_id==0) {
			$account_names[0] = ts('Unknown');
		} else {
			$btx_bao = new CRM_Banking_BAO_BankAccount();
			$btx_bao->get('id', $account_id);
			$data_parsed = $btx_bao->getDataParsed();
			if (isset($data_parsed['name'])) {
				$account_names[$account_id] = $data_parsed['name'];
			} else {
				$account_names[$account_id] = ts('account')." [$account_id]";
			}
		}
    }

    $this->assign('account_week_data', $account_week_data);
    $this->assign('account_names', $account_names);
    $this->assign('weeks', $weeks);

    // get statistics data
    $statistics = array();
    $statistics[] = $this->calculateStats(
    			ts("Payments")." (".ts("current year").")", 
    			"YEAR(value_date) = '".date('Y')."'",
    			$payment_states);
    $statistics[] = $this->calculateStats(
    			ts("Payments")." (".ts("last year").")", 
    			"YEAR(value_date) = '".date('Y', strtotime("-1 year"))."'",
    			$payment_states);
    $statistics[] = $this->calculateStats(
    			ts("Payments")." (".ts("all times").")", 
    			"1",
    			$payment_states);
	$this->assign('statistics', $statistics);
    
    parent::run();	
  }

  function calculateStats($name, $where_clause, $payment_states) {
  	$data = array('title' => $name, 'count' => 0, 'stats' => array());
  	foreach ($payment_states as $state) {
  		$data['stats'][$state['label']] = 0;
  	}
  	$stats_sql = "
  	SELECT
  	  COUNT(id)    		AS count,
  	  MIN(value_date)	AS first_payment,
  	  MAX(value_date)	AS last_payment,
  	  status_id			AS status_id
  	FROM
  	  civicrm_bank_tx
  	WHERE 
  	  $where_clause
  	GROUP BY
  	  status_id;
  	";
  	$stats = CRM_Core_DAO::executeQuery($stats_sql);
  	while ($stats->fetch()) {
  		$data['count'] += $stats->count;
  		$data['stats'][$payment_states[$stats->status_id]['label']] = $stats->count;
  		if (!isset($data['from'])) {
  			$data['from'] = $stats->first_payment;
  			$data['to'] = $stats->last_payment;
  		} else {
  			if ($data['from'] > $stats->first_payment) $data['from'] = $stats->first_payment;
  			if ($data['to']   < $stats->first_payment) $data['to'] = $stats->first_payment;
   		}
  	}
  	return $data;
  }
}