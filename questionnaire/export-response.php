<?php

$questionnaireResponse = $module->getFHIRResourceForRecord($_GET['id']);

header("Content-Disposition: attachment; filename=questionnaire-export.json");
echo $module->jsonSerialize($questionnaireResponse);
