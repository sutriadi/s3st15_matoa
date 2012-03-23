<?php
/**
 *
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Overdues Report */

// key to authenticate
define('INDEX_AUTH', '1');

// main system configuration
require '../../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Overdued List Report';
$reportView = false;
$num_recs_show = 20;
if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
?>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(__('Overdued List')); ?> - <?php echo __('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Member ID').'/'.__('Member Name'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::textField('text', 'id_name', '', 'style="width: 50%"');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Loan Date From'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::dateField('startDate', '2000-01-01');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Loan Date Until'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::dateField('untilDate', date('Y-m-d'));
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Record each page'); ?></div>
            <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo __('Set between 20 and 200'); ?></div>
        </div>
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo __('Apply Filter'); ?>" />
    <input type="button" name="moreFilter" value="<?php echo __('Show More Filter Options'); ?>" />
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
<?php
} else {
    ob_start();
    
	require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';
	require MODULES_BASE_DIR.'membership/member_base_lib.inc.php';
    require MODULES_BASE_DIR.'circulation/circulation_base_lib.inc.php';
    
    // table spec
    $table_spec = 'loan AS l
        LEFT JOIN item AS i ON l.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN member AS m ON l.member_id=m.member_id
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        LEFT JOIN mst_coll_type AS c ON i.coll_type_id=c.coll_type_id
        ';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn('m.member_id AS \''.__('Member ID').'\'',
        'm.inst_name AS \''.__('Institution').'\'',
        'm.pin AS \''.__('PIN').'\'',
        'l.item_code AS \''.__('Item Code').'\'',
        'b.title AS \''.__('Title').'\'',
        'l.loan_date AS \''.__('Loan Date').'\'',
        'l.due_date AS \''.__('Due Date').'\'',
        '(TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \''.__('Overdue Days').'\'',
        'l.loan_id AS \''.__('Fines').'\''
	);
    $reportgrid->setSQLorder('l.due_date DESC');
/*
    $reportgrid->sql_group_by = 'm.member_id';
*/

    $overdue_criteria = ' (l.is_lent=1 AND l.is_return=0 AND TO_DAYS(due_date) < TO_DAYS(\''.date('Y-m-d').'\')) ';
    // is there any search
    if (isset($_GET['id_name']) AND $_GET['id_name']) {
        $keyword = $dbs->escape_string(trim($_GET['id_name']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' (';
            foreach ($words as $word) {
                $concat_sql .= " (m.member_id LIKE '%$word%' OR m.member_name LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $overdue_criteria .= ' AND '.$concat_sql;
        } else {
            $overdue_criteria .= " AND m.member_id LIKE '%$keyword%' OR m.member_name LIKE '%$keyword%'";
        }
    }
    // loan date
    if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) {
        $date_criteria = ' AND (TO_DAYS(l.loan_date) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND
            TO_DAYS(\''.$_GET['untilDate'].'\'))';
        $overdue_criteria .= $date_criteria;
    }
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 5 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }
    $reportgrid->setSQLCriteria($overdue_criteria);

    // set table and table header attributes
    $reportgrid->table_attr = 'align="center" class="dataListPrinted" cellpadding="5" cellspacing="0"';
    $reportgrid->table_header_attr = 'class="dataListHeaderPrinted"';
/*
    $reportgrid->column_width = array('1' => '80%');
*/

	$curr_date = date('Y-m-d');
	function calcFine($obj_db, $array_data)
	{
		global $curr_date;
		$circulation = new circulation($obj_db, $array_data[0]);
		$circulation->holiday_dayname = $_SESSION['holiday_dayname'];
		$circulation->holiday_date = $_SESSION['holiday_date'];
		$fine = $circulation->countOverdueValue($array_data[8], $curr_date);
		return $fine['value'];
	}

    // callback function to show overdued list
    function showOverduedList($obj_db, $array_data)
    {
        global $date_criteria;

        // member name
        $member_q = $obj_db->query('SELECT member_name, member_email, member_phone, member_mail_address FROM member WHERE member_id=\''.$array_data[0].'\'');
        $member_d = $member_q->fetch_row();
        $member_name = $member_d[0];
        $member_mail_address = $member_d[3];
        unset($member_q);

        $ovd_title_q = $obj_db->query('SELECT l.item_code, i.price, i.price_currency,
            b.title, l.loan_date,
            l.due_date, (TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \'Overdue Days\'
            FROM loan AS l
                LEFT JOIN item AS i ON l.item_code=i.item_code
                LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
            WHERE (l.is_lent=1 AND l.is_return=0 AND TO_DAYS(due_date) < TO_DAYS(\''.date('Y-m-d').'\')) AND l.member_id=\''.$array_data[0].'\''.( !empty($date_criteria)?$date_criteria:'' ));
        $_buffer = '<div style="font-weight: bold; color: black; font-size: 10pt; margin-bottom: 3px;">'.$member_name.' ('.$array_data[0].')</div>';
        $_buffer .= '<div style="color: black; font-size: 10pt; margin-bottom: 3px;">'.$member_mail_address.'</div>';
        $_buffer .= '<div style="font-size: 10pt; margin-bottom: 3px;"><div id="'.$array_data[0].'emailStatus"></div>'.__('E-mail').': <a href="mailto:'.$member_d[1].'">'.$member_d[1].'</a> - <a class="usingAJAX" href="'.MODULES_WEB_ROOT_DIR.'membership/overdue_mail.php'.'" postdata="memberID='.$array_data[0].'" loadcontainer="'.$array_data[0].'emailStatus">Send Notification e-mail</a> - '.__('Phone Number').': '.$member_d[2].'</div>';
        $_buffer .= '<table width="100%" cellspacing="0">';
        while ($ovd_title_d = $ovd_title_q->fetch_assoc()) {
            $_buffer .= '<tr>';
            $_buffer .= '<td valign="top" width="10%">'.$ovd_title_d['item_code'].'</td>';
            $_buffer .= '<td valign="top" width="40%">'.$ovd_title_d['title'].'<div>'.__('Price').': '.$ovd_title_d['price'].' '.$ovd_title_d['price_currency'].'</div></td>';
            $_buffer .= '<td width="20%">'.__('Overdue').': '.$ovd_title_d['Overdue Days'].' '.__('day(s)').'</td>';
            $_buffer .= '<td width="30%">'.__('Loan Date').': '.$ovd_title_d['loan_date'].' &nbsp; '.__('Due Date').': '.$ovd_title_d['due_date'].'</td>';
            $_buffer .= '</tr>';
        }
        $_buffer .= '</table>';
        return $_buffer;
    }
    // modify column value
    $reportgrid->modifyColumnContent(8, 'callback{calcFine}');

    // put the result into variables
    echo '<form id="getcsvform" method="POST" action="./getcsv.php" target="_blank"><input type="hidden" name="csv_text" id="csv_text" />';
    printf('<button id="btnCSV">%s</button></form>', __('Download CSV Table'));
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);

    ?>
    <script type="text/javascript" src="<?php echo JS_WEB_ROOT_DIR.'jquery.js'; ?>"></script>
    <script type="text/javascript" src="<?php echo JS_WEB_ROOT_DIR.'updater.js'; ?>"></script>
    <script type="text/javascript" src="<?php echo JS_WEB_ROOT_DIR.'table2CSV.js'; ?>"></script>
    <script type="text/javascript">
    // registering event for send email button
    $(document).ready(function() {
        $('#btnCSV').click(function() {
			var csv_value=$('table[class=dataListPrinted]').table2CSV({delivery:'value'});
			$('#csv_text').val(csv_value);
			$('#getcsvform').submit();
		});
        
        parent.$('#pagingBox').html('<?php echo str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set) ?>');
        $('a.usingAJAX').click(function(evt) {
            evt.preventDefault();
            var anchor = $(this);
            // get anchor href
            var url = anchor.attr('href');
            var postData = anchor.attr('postdata');
            var loadContainer = anchor.attr('loadcontainer');
            if (loadContainer) { container = jQuery('#'+loadContainer); }
            // set ajax
            if (postData) {
                container.simbioAJAX(url, {method: 'post', addData: postData});
            } else {
                container.simbioAJAX(url, {addData: {ajaxload: 1}});
            }
        });
    });
    </script>
    <?php

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}
?>
