<table class="halfSection leftSection">
	<tr>
		<td colspan="20">
			<h4>Left Shoulder Joint</h4>
		</td>
	</tr>
	<?php 

		$list = $controller::$model->getShoulderJointListModel();

		foreach ($list as $listItem)
		{
			$controller::showMultiChoiceCommentInput(
				'left_' . $listItem['option_id'],
				$listItem['title']
			);
		}
	?>
</table>

<table class="halfSection rightSection">
	<tr>
		<td colspan="20">
			<h4>Right Shoulder Joint</h4>
		</td>
	</tr>
	<?php 

		foreach ($list as $listItem)
		{
			$controller::showMultiChoiceCommentInput(
				'right_' . $listItem['option_id'],
				$listItem['title']
			); 
		}
	?>
</table>