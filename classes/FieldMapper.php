<?php namespace Vanderbilt\FHIRServicesExternalModule;

use REDCap;
use Exception;

const INTEGER_PATTERN = "^-?([0]|([1-9][0-9]*))$";
const POSITIVE_INT_PATTERN = "^[1-9][0-9]*$";
const UNSIGNED_INT_PATTERN = "^[0]|([1-9][0-9]*)$";
const DATE_TIME_PATTERN = "^([0-9]([0-9]([0-9][1-9]|[1-9]0)|[1-9]00)|[1-9]000)(-(0[1-9]|1[0-2])(-(0[1-9]|[1-2][0-9]|3[0-1])(T([01][0-9]|2[0-3]):[0-5][0-9]:([0-5][0-9]|60)(\\.[0-9]+)?(Z|(\\+|-)((0[0-9]|1[0-3]):[0-5][0-9]|14:00)))?)?)?$";
const INSTANT_PATTERN = "^([0-9]([0-9]([0-9][1-9]|[1-9]0)|[1-9]00)|[1-9]000)-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])T([01][0-9]|2[0-3]):[0-5][0-9]:([0-5][0-9]|60)(\\.[0-9]+)?(Z|(\\+|-)((0[0-9]|1[0-3]):[0-5][0-9]|14:00))$";
const BOOLEAN_PATTERN = "^true|false$";
const DECIMAL_PATTERN = "^-?(0|[1-9][0-9]*)(\\.[0-9]+)?([eE][+-]?[0-9]+)?$";

class FieldMapper{
    /** @var FHIRServicesExternalModule */
    private $module;
    private $projectId;
    private $recordId;
    private $resources;

    function __construct($module, $projectId, $recordId){
        $this->module = $module;
        $this->projectId = $projectId;
        $this->recordId = $recordId;
        $this->resources = [];

        $recordIdFieldName = $this->getModule()->getRecordIdField($projectId);
        $mappings = $this->getMappings($projectId);

        // Add the record ID regardless so that the standard return format is used.
        // REDCap returns a different format without it.
        $mappedFieldNames = array_merge([$recordIdFieldName], $this->getMappingFieldNames($mappings));
        $fields = $mappedFieldNames;

        $unmappedUseQuestionnaire = $this->getModule()->getProjectSetting('unmapped-use-questionnaire', $projectId);
        if($unmappedUseQuestionnaire){
            $fields = null; //pull all fields;
        }

        $rows = json_decode(REDCap::getData($projectId, 'json', $recordId, $fields), true);
        $questionnaireFieldNames = [];
        foreach($rows as $data){
            foreach($data as $fieldName=>$value){
                $fieldNameForID = $fieldName;

                [$checkboxFieldName, $checkboxValue] = $this->parseCheckboxFieldName($fieldName);
                if($checkboxFieldName && $value === '1'){
                    $fieldName = $checkboxFieldName;
                    $value = $checkboxValue;
                }

                $mapping = $mappings[$fieldName] ?? null;
                if($value === ''){
                    continue;
                }

                if($mapping === null){
                    if($unmappedUseQuestionnaire){
                        $mapping = 'Questionnaire';
                    }
                    else{
                        continue;
                    }
                }
                
                if($mapping === 'Questionnaire'){
                    // TODO - The record ID field name is not the only one that needs to be taken into consideration here.
                    // It might makes sense to expose ALL unit testing fields in the framework to modules,
                    // so that I can try mapping a questionnaire with every possible combo of repeating fields,
                    // like that framework unit test Scott helped come up with the examples for.
                    if($fieldName !== $recordIdFieldName){
                        $questionnaireFieldNames[$fieldName] = true;
                    }

                    continue;
                }

                $isArrayMapping = is_array($mapping);
                $primaryElementSystemWasSet = false;
                if($isArrayMapping){
                    $primaryMapping = $mapping['type'] . '/' . $mapping['primaryElementPath'];
                    
                    $systemValue = $mapping['primaryElementSystem'] ?? null;
                    if(!empty($systemValue)){
                        $systemElementPath = preg_replace('/code$/', 'system', $primaryMapping);
                        $this->processElementMapping($data, $fieldName, $fieldNameForID, $systemValue, $systemElementPath, $isArrayMapping);
                        $primaryElementSystemWasSet = true;
                    }
                }
                else{
                    $primaryMapping = $mapping;
                }

                $this->processElementMapping($data, $fieldName, $fieldNameForID, $value, $primaryMapping, $isArrayMapping && !$primaryElementSystemWasSet);

                if($isArrayMapping){
                    $this->processAdditionalElements($fieldNameForID, $mapping, $data);
                }
            }
        }

        $this->processExtensions();
        $this->processQuestionnaires($projectId, $recordId, $questionnaireFieldNames, $rows);
    }

    function parseCheckboxFieldName($fieldName){
        $separator = '___';
        $parts = explode($separator, $fieldName);
        $fieldName = array_shift($parts);
        if($this->fieldExists($fieldName) && $this->getFieldType($fieldName) === 'checkbox'){
            $value = implode($separator, $parts);
            $value = $this->getCodeFromExtendedCheckboxCodeFormatted($fieldName, $value);

            return [$fieldName, $value];
        }
        
        return [null, null];
    }

    private function processQuestionnaires($projectId, $recordId, $questionnaireFieldNames, $rows){
        if(empty($questionnaireFieldNames)){
            return;
        }
        
        $result = $this->getModule()->query('
            select *
            from redcap_metadata
            where
                project_id = ?
                and field_name != concat(form_name, \'_complete\')
                and element_type != "file" -- TODO add support for file fields
            order by field_order
        ', [$projectId]);
        
        $fieldsByFormName = [];
        while($field = $result->fetch_assoc()){
            if(!isset($questionnaireFieldNames[$field['field_name']])){
                continue;
            }

            $fieldsByFormName[$field['form_name']][] = $field;
        }

        $project = new \Project($projectId);
        foreach($project->getUniqueEventNames() as $eventId=>$eventName){
            foreach($this->getModule()->getFormsForEventId($eventId) as $formName){
                $fields = $fieldsByFormName[$formName] ?? null;
                if($fields === null){
                    continue;
                }

                $formDisplayName = $project->forms[$formName]['menu'];
                $repeatingForms = $this->getModule()->getRepeatingForms($eventId);

                // TODO - Consider reporting these warnings somehow.  Maybe there is some metadata the questionnaire could hold that represents them,
                // or there could be placeholders for missing fields.
                [$questionnaire, $warnings] = $this->getModule()->createQuestionnaire($projectId, $formName, $formDisplayName, $fields, $repeatingForms, $eventName);
                $questionnaireResponse = $this->getModule()->buildQuestionnaireResponse($questionnaire, $projectId, $recordId, $rows, $eventName);

                if(empty($questionnaireResponse->getItem())){
                    // No data points exist for this Questionnaire.  Don't include it.
                    continue;
                }

                $questionnaireResponse->setId(null);
                $questionnaireResponse->setIdentifier(null);

                $convertToJSON = function($resource) use ($recordId, $formName, $eventName){
                    $resource = json_decode($this->getModule()->jsonSerialize($resource), true);
                    
                    if($resource['resourceType'] === 'Questionnaire'){
                        $recordId = null;
                    }

                    $this->getModule()->initResource($resource, $recordId, $formName, [
                        'redcap_event_name' => $eventName
                    ]);

                    return $resource;
                };

                $questionnaire = $convertToJSON($questionnaire);
                $questionnaireResponse = $convertToJSON($questionnaireResponse);

                $questionnaireResponse['questionnaire'] = $questionnaire['url'];

                foreach([$questionnaire, $questionnaireResponse] as $resource){
                    $this->resources[$resource['resourceType']][] = $resource;
                }
            }
        }
    }

    private function processExtensions(){
        foreach($this->resources as $type=>&$resources){
            foreach($resources as &$resource){
                if($type === 'Patient'){
                    foreach(['race', 'ethnicity', 'birthsex'] as $extensionName){
                        $mappedData = $resource['extension'][$extensionName] ?? null;
                        if($mappedData){
                            unset($resource['extension'][$extensionName]);
        
                            $this->getModule()->setAssociativeArrayValues($resource, 'identifier', [
                                'meta' => [
                                    'profile' => [
                                        "http://hl7.org/fhir/us/core/StructureDefinition/us-core-patient"
                                    ]
                                ]
                            ]);
        
                            if(in_array($extensionName, ['race', 'ethnicity'])){
                                $extension = [];
            
                                $extension['url'] = 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-' . $extensionName;
            
                                foreach(['ombCategory', 'detailed'] as $url){
                                    foreach($mappedData[$url] ?? [] as $code){
                                        $extension['extension'][] = [
                                            'url' => $url,
                                            'valueCoding' => [
                                                "system" => "urn:oid:2.16.840.1.113883.6.238",
                                                "code" => $code,
                                            ]
                                        ];
                                    }
                                }
        
                                $text = $mappedData['text'] ?? null;
                                if($text){
                                    $extension['extension'][] = [
                                        'url' => 'text',
                                        'valueString' => $text
                                    ];
                                }
                                
                            }
                            else if($extensionName === 'birthsex'){
                                $extension = [
                                    'url' => 'http://hl7.org/fhir/us/core/StructureDefinition/us-core-' . $extensionName,
                                    'valueCode' => $mappedData
                                ];
                            }

                            $resource['extension'][] = $extension;
                        }
                    }
                }
            }
        }
    }

    private function getMappingFieldNames($mappings){
        $fieldNames = [];
        foreach($mappings as $fieldName => $mapping){
            $fieldNames[$fieldName] = true;

            if(is_array($mapping)){
                foreach(($mapping['additionalElements'] ?? []) as $details){
                    $fieldName = $details['field'] ?? null;
                    if($fieldName !== null){
                        $fieldNames[$fieldName] = true;
                    }
                }
            }
        }

        return array_keys($fieldNames);
    }

    private function getModule(){
        return $this->module;
    }
    
    private function getProjectId(){
        return $this->projectId;
    }

    private function getRecordId(){
        return $this->recordId;
    }

    public function getResources(){
        $resources = [];

        foreach($this->resources as $type=>$children){
            foreach($children as $child){
                $resources[] = $child;
            }
        }

        return $resources;
    }

    private function getMappings($projectId){
        $metadata = REDCap::getDataDictionary($projectId, 'array');
        $mappings = [];
        foreach($metadata as $fieldName=>$details){
            $pattern = '/.*' . ACTION_TAG_PREFIX . '([^' . ACTION_TAG_SUFFIX . ']*)' . ACTION_TAG_SUFFIX . '.*/';
            preg_match($pattern, $details['field_annotation'], $matches);
            if(!empty($matches)){
                $value = $matches[1];

                if($value[0] === '{'){
                    $value = $this->actionTagDecode($value);
                }

                $mappings[$fieldName] = $value;     
            }
        }

        return $mappings;
    }

    private function actionTagDecode($value){
        $value = str_replace(SINGLE_QUOTE_PLACEHOLDER, ACTION_TAG_SUFFIX, $value);
        return json_decode($value, true);
    }

    static function actionTagEncode($value){
        if(is_array($value)){
            $value = json_encode($value);
        }
        
        return str_replace(ACTION_TAG_SUFFIX, SINGLE_QUOTE_PLACEHOLDER, $value); 
    }

    private function &getArrayChild(&$array, $addNewIfExists){
        if(empty($array)){
            $subPathIndex = 0;
        }
        else{
            $subPathIndex = count($array)-1;

            if($addNewIfExists){
                $subPathIndex++;
            }
        }

        return $array[$subPathIndex];
    }

    private function findSubPath($elementParts, $parentDefinition, &$subPath, $subResourceName, &$parentsSoFar = null, $hasParentArray = false){
        if($parentsSoFar === null){
            $parentsSoFar = [];
        }
        
        $elementPart = array_shift($elementParts);

        $property = $parentDefinition['properties'][$elementPart] ?? null;
        if($property === null){
            throw new Exception("Property named '$elementPart' not found in element path for the '$subResourceName' resource: " . json_encode($parentsSoFar, JSON_PRETTY_PRINT));
        }
        
        if(empty($elementParts)){
            $alreadySet = isset($subPath[$elementPart]) && ($property['type'] ?? null) !== 'array';
            return [&$subPath, $subResourceName, $parentDefinition, $alreadySet];
        }
        
        $subPath = &$subPath[$elementPart];
        $parentsSoFar[] = $elementPart;
        
        $definitions = SchemaParser::getDefinitions();
        $subResourceName = SchemaParser::getResourceNameFromRef($property);
        $parentDefinition = $definitions[$subResourceName] ?? null;

        $isArray = ($property['type'] ?? null) === 'array';
        if($isArray){
            $arrayParent = &$subPath;
            $subPath = &$this->getArrayChild($subPath, false);
        }

        $findSubPath = function(&$subPath) use ($elementParts, $parentDefinition, $subResourceName, &$parentsSoFar, $hasParentArray, $isArray){
            return $this->findSubPath($elementParts, $parentDefinition, $subPath, $subResourceName, $parentsSoFar, $hasParentArray || $isArray);
        };

        $response = $findSubPath($subPath);
        if($response[3] === true && $isArray && !$hasParentArray){
            $subPath = &$this->getArrayChild($arrayParent, true);
            $response = $findSubPath($subPath);
        }

        return $response;
    }

    private function processElementMapping($data, $fieldName, $fieldNameForID, $value, $mappingString, $addNewArrayItem){
        $parts = explode('/', $mappingString);
        $resourceName = array_shift($parts);
        $elementPath = implode('/', $parts);
        $elementParts = $parts;
        $elementName = array_pop($parts);

        if(empty($resourceName)){
            throw new Exception('Mapping is missing the resource type!');
        }

        $definitions = SchemaParser::getDefinitions();

        $resource = &$this->getArrayChild($this->resources[$resourceName], $addNewArrayItem && $this->getModule()->isRepeatableResource($resourceName));
        if(!isset($resource['id'])){
            $resource['resourceType'] = $resourceName;
            $this->getModule()->initResource($resource, $this->getRecordId(), $fieldNameForID, $data);
        }

        $subPath = &$resource;
        $subResourceName = $resourceName;
        $parentDefinition = $definitions[$resourceName];

        $response = $this->findSubPath($elementParts, $parentDefinition, $subPath, $subResourceName);
        $subPath = &$response[0];
        $subResourceName = $response[1];
        $parentDefinition = $response[2];
        $alreadySet = $response[3];

        $elementProperty = $parentDefinition['properties'][$elementName] ?? null;
        if($elementProperty === null){
            throw new Exception("The following mapping is not valid: $mappingString");
        }

        if($alreadySet){
            if(
                $resourceName === 'Patient'
                &&
                $elementPath === 'deceasedBoolean'
            ){
                /**
                 * This element is allowed to be mapped multiple times, since a deceased flag may exist on multiple events in REDCap.
                 * If ANY of those mapped values is true, then we should just continue and ignore any false values.
                 * Remember, this loop may not process events in chronological order.
                 */
                if(@$subPath[$elementName] === true){
                    return;
                }
            }
            else{
                throw new StackFreeException("The '$mappingString' element might be unintentionally mapped to multiple fields.  You can check this by opening the Codebook and searching for '$elementPath' and seeing if it is mapped multiple times for the '$resourceName' resource.  If only one instance is found, this could also be explained by multiple values existing for the same field in different contexts (ex: events).  Until we can add some sort of 'Repeatable Context' feature, the best work around for this is to add an additional field mapping (any field or value) to signify that an additional instance of the resource needs to be created.");
            }
        }

        // The java FHIR validator does not allow leading or trailing whitespace.
        $value = trim($value);
        if($value === ''){
            // In FHIR, empty values should just not be specified in the first place.
            return;
        }

        $modifiedElementProperty = SchemaParser::getModifiedProperty($resourceName, $elementPath);

        $choices = null;
        if($subResourceName === 'Coding'){
            $system = $subPath['system'] ?? null;
            if(empty($system)){
                list($ontologyCategory, $ontologySystem) = $this->getOntologyCategoryAndSystem($fieldName);
                if(!empty($ontologySystem)){
                    $system = $ontologySystem;
                    $display = \Form::getWebServiceCacheValues($this->getProjectId(), 'BIOPORTAL', $ontologyCategory, $value);
                    if(!empty($display)){
                        $subPath['display'] = $display;
                    }
                }
                else{
                     /**
                     * A system was not specified.  Use the default.
                     * This is likely not necessary for new primary element mappings now that a system can be specified.
                     * However, we probably want to leave it in place for previously existing primary element mappings that don't explicitly include the system.
                     */
                    $system = $modifiedElementProperty['system'] ?? null;
                }

                if($system !== null){
                    $subPath['system'] = $system;
                }
            }
            else{
                $choices = SchemaParser::getCodesBySystem()[$system] ?? null;
            }

            if($elementName === 'code' && !empty($fieldName)){
                $type = $this->getFieldType($fieldName);
                if(in_array($type, ['select', 'radio', 'checkbox'])){
                    $subPath['display'] = $this->getChoiceLabel($fieldName, $value);
                }
            }
        }

        if($choices === null){
            $choices = $modifiedElementProperty['redcapChoices'] ?? null;
        }

        if($choices !== null){
            $value = $this->getMatchingChoiceValue($this->getProjectId(), $fieldName, $value, $choices);
        }
        
        $ref = SchemaParser::getResourceNameFromRef($modifiedElementProperty);
        
        if($ref === null){
            $pattern = $modifiedElementProperty['pattern'] ?? null;
        }
        else{
            $pattern = $definitions[$ref]['pattern'] ?? null;
        }

        if($pattern === BOOLEAN_PATTERN){
            if($value === 'true' || $value === '1'){
                $value = true;
            }
            else if($value === 'false' || $value === '0'){
                $value = false;
            }
        }
        else if(in_array($pattern, [DATE_TIME_PATTERN, INSTANT_PATTERN])){
            $value = $this->getModule()->formatFHIRDateTime($value);
        }
        else if(in_array($pattern, [INTEGER_PATTERN, POSITIVE_INT_PATTERN, UNSIGNED_INT_PATTERN])){
            $intValue = (int) $value; // This handles positive & negative numbers
            if($intValue == $value){
                $value = $intValue;
            }
            else{
                throw new \Exception("Expected an integer value for the '$fieldName' field but found '$value' instead.");
            }
        }
        else if($pattern === DECIMAL_PATTERN){
            $newValue = (float) $value;
            if((string)$newValue === $value){
                $value = $newValue;
            }
            else{
                throw new \Exception("Expected a decimal value for the '$fieldName' field but found '$value' instead.");
            }
        }

        if(($elementProperty['type'] ?? null) === 'array'){
            $subPath[$elementName][] = $value;
        }
        else{
            $subPath[$elementName] = $value;
        }
    }

    private function fieldExists($fieldName){
        return isset($this->getModule()->getProject()->metadata[$fieldName]);
    }

    private function getMetadata($fieldName){
        return $this->getModule()->getProject()->metadata[$fieldName];
    }

    private function getFieldType($fieldName){
        return $this->getMetadata($fieldName)['element_type'];
    }

    function getCodeFromExtendedCheckboxCodeFormatted($fieldName, $formattedCode){
        $lines = explode("\\n", $this->getMetadata($fieldName)['element_enum']);
        foreach($lines as $line){
            $firstComma = strpos($line, ',');
            $code = trim(substr($line, 0, $firstComma));
            if($formattedCode === \Project::getExtendedCheckboxCodeFormatted($code)){
                return $code;
            }
        }
        
        throw new \Exception("Formatted code '$formattedCode' not found for field '$fieldName'!");
    }

    private function getChoiceLabel($fieldName, $value){
        $lines = explode("\\n", $this->getMetadata($fieldName)['element_enum']);
        foreach($lines as $line){
            $firstComma = strpos($line, ',');
            $code = trim(substr($line, 0, $firstComma));
            $label = trim(substr($line, $firstComma+1));

            if($code === $value){
                return $label;
            }
        }
        
        throw new \Exception("Code '$value' not found for field '$fieldName'!");
    }
    
    function getOntologyCategoryAndSystem($fieldName){
        $category = '';
        $system = '';
        
        if(!empty($fieldName)){
            $enum = $this->getMetadata($fieldName)['element_enum'];
            $ontologyParts = explode('BIOPORTAL:', $enum);
            if(count($ontologyParts) === 2 && $ontologyParts[0] === ''){
                $category = $ontologyParts[1];
                $system = $this->getModule()->getOntologySystems()[$category] ?? null;
            }
        }
        
        return [$category, $system];
    }

    private function processAdditionalElements($fieldNameForID, $mapping, $data){
        $resource = $mapping['type'];
        foreach(($mapping['additionalElements'] ?? []) as $details){
            $fieldName = $details['field'] ?? null;
            if($fieldName === null){
                $value = $details['value'];
            }
            else{
                $value = $data[$fieldName];
            }
            
            $this->processElementMapping($data, $fieldName, $fieldNameForID, $value, "$resource/{$details['element']}", false);
        }
    }

    private function getMatchingChoiceValue($projectId, $fieldName, $value, $choices){
        // An example of a case where it makes sense to match the label instead of the value is a REDCap gender value of 'F' with a label of 'Female'.
        $label = strtolower($this->getModule()->getChoiceLabel(['project_id'=>$projectId, 'field_name'=>$fieldName, 'value'=>$value]));
        $possibleValues = [$value, $label];

        $lowerCaseMap = [];
        foreach($choices as $code => $label){
            $code = (string) $code; // PHP forces int keys for arrays if they're integers, but FHIR expects all codes to always be strings.
            $lowerCaseMap[strtolower($label)] = $code;
            $lowerCaseMap[strtolower($code)] = $code;
        }

        foreach($possibleValues as $possibleValue){
            // Use lower case strings for case insensitive matching.
            $matchedValue = $lowerCaseMap[strtolower($possibleValue)] ?? null;
            if($matchedValue !== null){
                return $matchedValue;
            }
        }

        return $value;
    }
}