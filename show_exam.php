<?php

if(isset($_REQUEST['action']) and $_REQUEST['action']=='show_exam_result' ) { // Initial setup for ajax.
	if (!function_exists('add_action')) {
		$wp_root = '../../..';
		if (file_exists($wp_root.'/wp-load.php')) {
			require_once($wp_root.'/wp-load.php');
		} else {
			require_once($wp_root.'/wp-config.php');
		}
	}
	$exam_id = $_REQUEST['quiz_id'];
}

require_once('wpframe.php');

if(!is_single() and isset($GLOBALS['watu_client_includes_loaded'])) { #If this is in the listing page - and a quiz is already shown, don't show another.
	printf(t("Please go to <a href='%s'>%s</a> to view the test"), get_permalink(), get_the_title());
} else {

global $wpdb;
$GLOBALS['wpframe_plugin_name'] = basename(dirname(__FILE__));
$GLOBALS['wpframe_plugin_folder'] = $GLOBALS['wpframe_wordpress'] . '/wp-content/plugins/' . $GLOBALS['wpframe_plugin_name'];

$answer_display = get_option('watu_show_answers');
$all_question = $wpdb->get_results($wpdb->prepare("SELECT ID,question, answer_type FROM {$wpdb->prefix}watu_question WHERE exam_id=%d ORDER BY ID", $exam_id));
if($all_question) {
	if(!isset($GLOBALS['watu_client_includes_loaded']) and !isset($_REQUEST['action']) ) {
?>
<link type="text/css" rel="stylesheet" href="<?php echo $GLOBALS['wpframe_plugin_folder']?>/style.css" />
<script type="text/javascript" src="<?php echo $GLOBALS['wpframe_wordpress']?>/wp-includes/js/jquery/jquery.js"></script>
<script type="text/javascript" src="<?php echo $GLOBALS['wpframe_plugin_folder']?>/script.js"></script>
<?php
	$GLOBALS['watu_client_includes_loaded'] = true; // Make sure that this code is not loaded more than once.
}


if(isset($_REQUEST['action']) and $_REQUEST['action']) { // Quiz Reuslts.
	$score = 0;
	$achieved = 0;
	$total = 0;
/* Array ( [p] => 1 [question_id] => Array ( [0] => 1 [1] => 2 ) [answer-1] => Array ( [0] => 12 [1] => 13 [2] => 14 ) [answer-2] => Array ( [0] => 9 ) [action] => Show Results [quiz_id] => 1 )  */
	//print_r($_REQUEST);	exit;
	$result = '';
	$result .= "<p>" . t('All the questions in the exam along with their answers are shown below. Your answers are bolded. The correct answers have a green background while the incorrect ones have a red background.') . "</p>";

	foreach ($all_question as $ques) {
		$result .= "<div class='show-question'>";
		$result .= "<div class='show-question-content'>". stripslashes($ques->question) . "</div>\n";
		$all_answers = $wpdb->get_results("SELECT ID,answer,correct, point FROM {$wpdb->prefix}watu_answer WHERE question_id={$ques->ID} ORDER BY sort_order");

		$correct = false;
		$result .= "<ul>";
		$ansArr = is_array( $_REQUEST["answer-" . $ques->ID] )? $_REQUEST["answer-" . $ques->ID] : array();
		foreach ($all_answers as $ans) {
			$class = 'answer';
			if(  in_array($ans->ID , $ansArr) ) $class .= ' user-answer';
			if($ans->correct == 1) $class .= ' correct-answer';
			if( in_array($ans->ID , $ansArr ) and $ans->correct == 1) {$correct = true; $score+=$ans->point;}
			if( in_array($ans->ID , $ansArr ) ) $achieved+=$ans->point; 
			$result .= "<li class='$class'><span class='answer'>" . stripslashes($ans->answer) . "</span></li>\n";
		}
		$result .= "</ul>";
		if(!$_REQUEST["answer-" . $ques->ID]) $result .= "<p class='unanswered'>" . t('Question was not answered') . "</p>";

		$result .= "</div>";
		//$total++;
	}
	$total = $wpdb->get_var($wpdb->prepare("SELECT sum(point) FROM `{$wpdb->prefix}watu_question` as q inner join `{$wpdb->prefix}watu_answer` as a on question_id=q.ID WHERE `exam_id`=%d and correct='1' ", $exam_id));

	//Find scoring details of this guy.
	$percent = number_format($score / $total * 100, 2);
						//0-9			10-19%,	 	20-29%, 	30-39%			40-49%
	$all_rating = array(t('Failed'), t('Failed'), t('Failed'), t('Failed'), t('Just Passed'),
						//																			100%			More than 100%?!
					t('Satisfactory'), t('Competent'), t('Good'), t('Very Good'),t('Excellent'), t('Unbeatable'), t('Cheater'));
	$rate = intval($percent / 10);
	if($percent == 100) $rate = 9;
	if($score == $total) $rate = 10;
	if($percent>100) $rate = 11;
	$rating = $all_rating[$rate];
	
	$grade = 'None';
	$allGrades = $wpdb->get_results(" SELECT * FROM `{$wpdb->prefix}watu_grading` WHERE exam_id=$exam_id ");
	if( count($allGrades) ){
		foreach($allGrades as $grow ) {
//print_r($grow); echo '<br>';
			if( $grow->gfrom <= $achieved and $achieved <= $grow->gto ) {
				$grade = $grow->gtitle;
				if(!empty($grow->gdescription)) $grade.="<p>".$grow->gdescription."</p>";
				//
				break;
			}
		}
	}
	$score = $achieved;

	$quiz_details = $wpdb->get_row($wpdb->prepare("SELECT name,final_screen, description FROM {$wpdb->prefix}watu_master WHERE ID=%d", $exam_id));

	$replace_these	= array('%%SCORE%%', '%%TOTAL%%', '%%PERCENTAGE%%', '%%GRADE%%', '%%RATING%%', '%%CORRECT_ANSWERS%%', '%%WRONG_ANSWERS%%', '%%QUIZ_NAME%%',	  '%%DESCRIPTION%%');
	$with_these		= array($score,		 $total,	  $percent,			$grade,		 $rating,		$score,					$total-$score,	   stripslashes($quiz_details->name), stripslashes($quiz_details->description));

	// Show the results

	print str_replace($replace_these, $with_these, stripslashes($quiz_details->final_screen));
	if($answer_display == 1) print '<hr />' . $result;
	exit;// Exit due to ajax call

} else { // Show The Test
	$single_page = get_option('watu_single_page');
?>

<div id="watu_quiz" class="quiz-area <?php if($single_page) echo 'single-page-quiz'; ?>">
<form action="" method="post" class="quiz-form" id="quiz-<?php echo $exam_id?>">
<?php
$question_count = 1;
$question_ids = '';
foreach ($all_question as $ques) {
	echo "<div class='watu-question' id='question-$question_count'>";
	echo "<div class='question-content'>". stripslashes($ques->question) . "</div><br />";
	echo "<input type='hidden' name='question_id[]' value='{$ques->ID}' />";
	$question_ids .= $ques->ID.',';
	$dans = $wpdb->get_results("SELECT ID,answer,correct FROM {$wpdb->prefix}watu_answer WHERE question_id={$ques->ID} ORDER BY sort_order");
	$ans_type = $ques->answer_type;
	foreach ($dans as $ans) {
		if($answer_display == 2) {
			$answer_class = 'wrong-answer-label';
			if($ans->correct) $answer_class = 'correct-answer-label';
		}
		echo "<input type='$ans_type' name='answer-{$ques->ID}[]' id='answer-id-{$ans->ID}' class='answer answer-$question_count $answer_class answerof-{$ques->ID}' value='{$ans->ID}' />";
		echo "&nbsp;<label for='answer-id-{$ans->ID}' id='answer-label-{$ans->ID}' class='$answer_class answer label-$question_count'><span>" . stripslashes($ans->answer) . "</span></label><br />";
	}

	echo "</div>";
	$question_count++;
}
echo "<div style='display:none' id='question-$question_count'>";
echo "<br /><div class='question-content'><img src=\"".plugins_url('watu/loading.gif')."\" width=\"16\" height=\"16\" alt=\"".__('Loading', 'watu')." ...\" title=\"".__('Loading', 'watu')." ...\" />&nbsp;".__('Loading', 'watu')." ...</div><br />";
echo "</div>";
$question_ids = preg_replace('/,$/', '', $question_ids );
?><br />
<?php if($answer_display == 2) { ?>
<input type="button" id="show-answer" value="<?php e("Show Answer") ?>"  /><br />
<?php } else { ?>
<input type="button" id="next-question" value="<?php e("Next") ?> &gt;"  /><br />
<?php } ?>

<input type="button" name="action" onclick="submitResult()" id="action-button" value="<?php e("Show Results") ?>"  />
<input type="hidden" name="quiz_id" value="<?php echo  $exam_id ?>" />
</form>
</div>
<script type="text/javascript">
var question_ids = "<?php print $question_ids ?>";
var exam_id = <?php print $exam_id ?>;
var qArr = question_ids.split(',');
var url = "<?php print plugins_url('watu/'.basename(__FILE__) ) ?>";
/* for(x in qArr) {
	ansgroup = '.answerof-'+qArr[x];
	jQuery(ansgroup).each(function(){
		alert( jQuery(this).is(':checked') );
	});
} */
</script>
<?php }
}
}
?>