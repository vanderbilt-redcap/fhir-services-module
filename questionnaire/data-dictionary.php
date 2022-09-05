<div class="projhdr">Questionnaire Data Dictionary Options</div>
<br>
<?php

$logId = @$_POST['log-id'];
if($logId){
    $log = $module->getReceivedResource($logId);

    $tempFilePath = tempnam(sys_get_temp_dir(), 'received-questionnaire');
    file_put_contents($tempFilePath, $log['content']);

    $uploadedFile = [
        'name' => 'Received Questionnaire ' . $log['log_id'] . '.json',
        'tmp_name' => $tempFilePath,
        'size' => filesize($tempFilePath)
    ];
}
else{
    $uploadedFile = @$_FILES['questionnaire'];
}

if($uploadedFile){
    $module->replaceDataDictionaryWithQuestionnaire($uploadedFile);
    if($tempFilePath){
        unlink($tempFilePath);
    }

    ?><div class='darkgreen'>Data Dictionary Successfully Replaced</div><br><?php
}

$edoc = $module->getQuestionnaireEDoc();
if($edoc){
    ?>
    <p>This project's Data Dictionary was last generated from the following FHIR Questionnaire, and should not be modified manually:</p>
    <a href='<?=$module->getUrl('download-questionnaire.php')?>' style='text-decoration: underline'><?=htmlspecialchars($edoc['doc_name'], ENT_QUOTES)?></a>
    <?php
}

?>
<br>
<br>
<br>
<p>Upload a FHIR Questionnaire to replace the Data Dictionary on this project.  Either JSON or XML format is supported.
<br><br>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="questionnaire"><br><br>
    <button>Upload</button>
</form>
<br>