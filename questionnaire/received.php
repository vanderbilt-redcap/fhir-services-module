<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaire;


$result = $module->getReceivedResources();
$rows = [];
while($row = $result->fetch_assoc()){
    $o = $module->parse($row['content']);;
    
    $type = $row['type'];
    if($type === 'Questionnaire'){
        $title = $o->getTitle();
    }
    else if($type === 'QuestionnaireResponse'){
        $title = $module->getText($o->getItem()[0]);
    }
    else if($type === 'Binary'){
        $contentType = $module->getValue($o->getContentType());
        $extension = $module->getExtensionForMIMEType($contentType);
      
        if($extension){
            $title = strtoupper($extension);
            $row['download'] = true;
        }
        else{
            $title = 'Unknown Content Type: ' . $contentType;
        }
    }

    $row['title'] = $title;

    $rows[$row['log_id']] = $row;
}

?>

<style>
    table.recevied-questionnaires{
        max-width: 900px;
    }

    table.recevied-questionnaires th{
        font-weight: bold;
    }
</style>

<div class="projhdr">Received Resources</div>
<table class='table recevied-questionnaires'>
    <tr>
        <th>ID</th>
        <th>Date/Time</th>
        <th>Type</th>
        <th>Title</th>
        <th></th>
    </tr>
    <?php
    foreach($rows as $logId=>$row){
        $type = $row['type'];
        ?>
        <tr data-log-id="<?=$logId?>">
            <td><?=$logId?></td>
            <td><?=$row['timestamp']?></td>
            <td><?=$type?></td>
            <td><?=$row['title']?></td>
            <td>
                <button class='details'>Show Details</button>
                <?php
                if($type === 'Questionnaire') {
                    ?><button class='replace-data-dictionary'>Replace Data Dictionary</button><?php
                }
                else if($type === 'Binary') {
                    ?><button class='download'>Download</button><?php
                }
                ?>
            </td>
        </tr>
        <?php
    }   
    ?>
</table>


<script>
    $(function(){
        var data = <?=json_encode($rows)?>;
        var table = $('table.recevied-questionnaires')
        
        var getLogId = function(element){
            return $(element).closest('tr').data('log-id')
        }

        table.find('button.details').click(function(){
            var logId = getLogId(this)

            var width = window.innerWidth - 100;
            var height = window.innerHeight - 200;
            var content = '<pre style="max-height: ' + height + 'px">' + data[logId]['content'] + '</pre>'

            simpleDialog(content, 'Details', null, width)
        })
        
        table.find('button.replace-data-dictionary').click(function(){
            var logId = getLogId(this)

            var form = $("<form style='display: none'><input name='log-id' value='" + logId + "'></form>")[0]
            form.method = 'POST'
            form.action = <?=json_encode($module->getQuestionnaireUrl('data-dictionary'))?>;

            $('body').append(form)
            
            form.submit()  
        })

        table.find('button.download').click(function(){
            window.open(<?=json_encode($module->getUrl('download-binary-resource.php') . '&log-id=')?> + getLogId(this))
        })
    })
</script>