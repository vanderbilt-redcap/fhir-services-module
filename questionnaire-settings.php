<div class="projhdr">Questionnaire Settings</div>

<br>
<br>
<h5>Data Dictionary</h5>
<br>
<p>Upload a FHIR Questionnaire to replace the Data Dictionary on this project.  Either JSON or XML format is supported.
<br><br>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="questionnaire"><br><br>
    <button>Upload</button>
</form>
<br>
<?php

$uploadedFile = @$_FILES['questionnaire'];
if($uploadedFile){
    $module->saveQuestionnaire($uploadedFile);
    ?><h6>Upload successful</h6><?php
}

$edoc = $module->getQuestionnaireEDoc();
if($edoc){
    ?>
    <br>
    <p>This project's Data Dictionary was last generated from the following FHIR Questionnaire, and should not be modified manually:</p>
    <a href='<?=$module->getUrl('download-questionnaire.php')?>' style='text-decoration: underline'><?=$edoc['doc_name']?></a>
    <?php
}

?>
<br>
<br>
<br>
<br>
<br>
<h5>Import</h5>
<br>
<form id='fhir-import-questionnaire-response-form' action='<?=$module->getUrl('import-response.php')?>' method='post' enctype='multipart/form-data'>
    <button>Import a FHIR QuestionnaireResponse</button>
    <input type='file' name='import' style='display: none'>
</form>
<script>
    $(function(){
        var form = $('#fhir-import-questionnaire-response-form')
        var input = form.find('input')

        input.change(function(){
            form.submit()
        })

        form.find('button').click(function(e){
            e.preventDefault()
            input.click()
        })
    })
</script>