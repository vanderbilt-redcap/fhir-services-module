<?php

$edoc = $module->getQuestionnaireEDoc();

header("Content-Disposition: attachment; filename={$edoc['doc_name']}");
readfile_chunked(EDOC_PATH . $edoc['stored_name']);