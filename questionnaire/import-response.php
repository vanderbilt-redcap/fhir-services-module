<?php

$logId = @$_GET['log-id'];
if($logId){
    $log = $module->getReceivedResource($logId);
    $resource = $log['content'];
}
else{
    $resource = file_get_contents($_FILES['import']['tmp_name']);
}

$csv = $module->questionnaireResponseToREDCapExport($resource);
$postUrl = APP_PATH_WEBROOT . "/index.php?pid=" . $module->getProjectId() . "&route=DataImportController:index";
?>

<script>
    var formData = new FormData()
    formData.append('format', 'rows')
    formData.append('overwriteBehavior', 'normal')
    formData.append('forceAutoNumber', '1')
    formData.append('submit', 'Upload File')
    formData.append('uploadedfile', new File([new Blob([<?=json_encode($csv)?>])], 'upload.csv'))
    formData.append('redcap_csrf_token', <?=json_encode(System::getCsrfToken())?>)

    xhr = new XMLHttpRequest()
    xhr.onreadystatechange = function(){
        if (xhr.readyState == XMLHttpRequest.DONE){
            document.open()
            document.write(xhr.responseText)
            document.close()
        }
    }

    xhr.open('POST', <?=json_encode($postUrl)?>);
    xhr.send(formData);
</script>