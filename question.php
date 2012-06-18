<?php
require('wpframe.php');
wpframe_stop_direct_call(__FILE__);

if( isset($_REQUEST['grade']) ) wpframe_message($_REQUEST['grade']);
$action = 'new';
if($_REQUEST['action'] == 'edit') $action = 'edit';

if(isset($_REQUEST['submit'])) {
	if($action == 'edit'){ //Update goes here
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}watu_question SET question=%s, answer_type=%s WHERE ID=%d", $_REQUEST['content'], $_REQUEST['answer_type'], $_REQUEST['question']));
		$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}watu_answer WHERE question_id=%d", $_REQUEST['question']));
//$wpdb->show_errors(); $wpdb->print_error();
		wpframe_message('Question updated.');

	} else {

	$sql = $wpdb->prepare("INSERT INTO {$wpdb->prefix}watu_question (exam_id, question, answer_type) VALUES(%d, %s, %s)", $_REQUEST['quiz'], $_REQUEST['content'], $_REQUEST['answer_type']);
		$wpdb->query($sql);//Inserting the questions
//$wpdb->show_errors(); $wpdb->print_error();		
		wpframe_message('Question added.');
		$_REQUEST['question'] = $wpdb->insert_id;
		$action='edit';
	}
	
	$question_id = $_REQUEST['question'];
	if($question_id>0) {
		// the $counter will skip over empty answers, $sort_order_counter will track the provided answers order.
		$counter = 1;
		$sort_order_counter = 1;
		$correctArry = $_REQUEST['correct_answer'];
		$pointArry = $_REQUEST['point'];
		foreach ($_REQUEST['answer'] as $key => $answer_text) {
			$correct=0;
			if( in_array($counter, $correctArry) ) $correct=1;
			$point = $pointArry[$key];
			if($answer_text) {
				$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}watu_answer(question_id,answer,correct,point, sort_order)
					VALUES(%d, %s, %s, %d, %d)", $question_id, $answer_text, $correct, $point, $sort_order_counter));
				$sort_order_counter++;
			}
			$counter++;
		}
	}
}


if($_REQUEST['message'] == 'new_quiz') {
	wpframe_message('New Exam added');
}

if($_REQUEST['action'] == 'delete') {
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}watu_answer WHERE question_id=%d", $_REQUEST['question']));
	$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}watu_question WHERE ID=%d", $_REQUEST['question']));
	wpframe_message('Question Deleted.');
}
$exam_name = stripslashes($wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}watu_master WHERE ID=%d", $_REQUEST['quiz'])));
?>

<div class="wrap">
<h2><?php echo t("Manage Questions in ") . $exam_name; ?></h2>

<p><a href="tools.php?page=watu/exam.php">Back to exams</a></p>

<?php
wp_enqueue_script( 'listman' );
wp_print_scripts();
?>

<p style="color:green;"><?php e('To add this exam to your blog, insert the code ') ?> <b>[WATU <?php echo $_REQUEST['quiz'] ?>]</b> <?php e('into any post.') ?></p>

<table class="widefat">
	<thead>
	<tr>
		<th scope="col"><div style="text-align: center;">#</div></th>
		<th scope="col"><?php e('Question') ?></th>
		<th scope="col"><?php e('Number Of Answers') ?></th>
		<th scope="col" colspan="3"><?php e('Action') ?></th>
	</tr>
	</thead>

	<tbody id="the-list">
<?php
// Retrieve the questions
$all_question = $wpdb->get_results("SELECT Q.ID,Q.question,(SELECT COUNT(*) FROM {$wpdb->prefix}watu_answer WHERE question_id=Q.ID) AS answer_count
										FROM `{$wpdb->prefix}watu_question` AS Q
										WHERE Q.exam_id=$_REQUEST[quiz] ORDER BY Q.ID");

if (count($all_question)) {
	$bgcolor = '';
	$class = ('alternate' == $class) ? '' : 'alternate';
	$question_count = 0;
	foreach($all_question as $question) {
		$question_count++;
		print "<tr id='question-{$question->ID}' class='$class'>\n";
		?>
		<th scope="row" style="text-align: center;"><?php echo $question_count ?></th>
		<td><?php echo stripslashes($question->question) ?></td>
		<td><?php echo $question->answer_count ?></td>
		<td><a href='edit.php?page=watu/question_form.php&amp;question=<?php echo $question->ID?>&amp;action=edit&amp;quiz=<?php echo $_REQUEST['quiz']?>' class='edit'><?php e('Edit'); ?></a></td>
		<td><a href='edit.php?page=watu/question.php&amp;action=delete&amp;question=<?php echo $question->ID?>&amp;quiz=<?php echo $_REQUEST['quiz']?>' class='delete' onclick="return confirm('<?php echo addslashes(t("You are about to delete this question. This will delete the answers to this question. Press 'OK' to delete and 'Cancel' to stop."))?>');"><?php e('Delete')?></a></td>
		</tr>
<?php
		}
	} else {
?>
	<tr style='background-color: <?php echo $bgcolor; ?>;'>
		<td colspan="4"><?php e('No questiones found.') ?></td>
	</tr>
<?php
}
?>
	</tbody>
</table>

<a href="edit.php?page=watu/question_form.php&amp;action=new&amp;quiz=<?php echo $_REQUEST['quiz'] ?>"><?php e('Create New Question')?></a>
</div>