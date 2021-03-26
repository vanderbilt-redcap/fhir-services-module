<?php namespace Vanderbilt\FHIRServicesExternalModule;

use REDCap;

class FieldMapper{
    function __construct($module, $projectId, $recordId){
        $this->module = $module;
        $this->projectId = $projectId;
        $this->recordId = $recordId;
        $this->resources = [];

        $mappings = $this->getMappings($projectId);

        // Add the record ID regardless so that the standard return format is used.
        // REDCap returns a different format without it.
        $fields = array_merge([$this->getModule()->getRecordIdField($projectId)], $this->getMappingFieldNames($mappings));
        $data = json_decode(REDCap::getData($projectId, 'json', $recordId, $fields), true)[0];
        foreach($data as $fieldName=>$value){
            $mapping = @$mappings[$fieldName];
            if($value === '' || $mapping === null){
                continue;
            }

            if(is_array($mapping)){
                $this->processElementMapping($fieldName, $value, $mapping['type'] . '/' . $mapping['primaryElementPath'], true);
                $this->processAdditionalElements($mapping, $data);
            }
            else{
                $this->processElementMapping($fieldName, $value, $mapping, false);
            }
        }

        return $this->resources;
    }

    private function getMappingFieldNames($mappings){
        $fieldNames = [];
        foreach($mappings as $fieldName => $mapping){
            $fieldNames[$fieldName] = true;

            if(is_array($mapping)){
                foreach($mapping['additionalElements'] as $details){
                    $fieldName = @$details['field'];
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
        return $this->resources;
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
        $value = json_encode($value);
        return str_replace(ACTION_TAG_SUFFIX, SINGLE_QUOTE_PLACEHOLDER, $value); 
    }

    private function processElementMapping($fieldName, $value, $mappingString, $addNewArrayItem){
        $parts = explode('/', $mappingString);
        $resourceName = array_shift($parts);
        $elementPath = implode('/', $parts);
        $elementName = array_pop($parts);
        $elementParents = $parts;

        $definitions = SchemaParser::getDefinitions();

        if(!isset($this->resources[$resourceName])){
            $this->resources[$resourceName] = [
                'resourceType' => $resourceName,
                'id' => $this->getModule()->getRecordFHIRId($this->getProjectId(), $this->getRecordId())
            ];
        }

        $resource = &$this->resources[$resourceName];
        
        $subPath = &$resource;
        $parentProperty = [
            'type' => null
        ];
        $parentDefinition = $definitions[$resourceName];

        foreach($elementParents as $parentName){
            $subPath = &$subPath[$parentName];
            $parentProperty = $parentDefinition['properties'][$parentName];
            $subResourceName = SchemaParser::getResourceNameFromRef($parentProperty);
            $parentDefinition = $definitions[$subResourceName];

            if($parentProperty['type'] === 'array'){
                if(empty($subPath)){
                    $subPathIndex = 0;
                }
                else{
                    $subPathIndex = count($subPath);

                    if(!$addNewArrayItem){
                        $subPathIndex--;
                    }
                }

                $subPath =& $subPath[$subPathIndex];
            }
        }

        $elementProperty = $parentDefinition['properties'][$elementName];
        if($elementProperty['type'] !== 'array' && isset($subPath[$elementName])){
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
                throw new StackFreeException("The '$mappingString' element is currently mapped to multiple fields, but should only be mapped to a single field.  It is recommended to view the Codebook and search for '$mappingString' to determine which field mapping(s) need to be modified.");
            }
        }

        // The java FHIR validator does not allow leading or trailing whitespace.
        $value = trim($value);
        if($value === ''){
            // In FHIR, empty values should just not be specified in the first place.
            return;
        }

        /**
         * This can't currently be combined with $elementProperty above
         * because of special handling (pseudo-elements like Patient/telecom/mobile/phone/value).
         */
        $modifiedElementProperty = SchemaParser::getModifiedProperty($resourceName, $elementPath);

        $choices = $modifiedElementProperty['redcapChoices'];
        $ref = SchemaParser::getResourceNameFromRef($modifiedElementProperty);
        if($choices !== null){
            $value = $this->getMatchingChoiceValue($this->getProjectId(), $fieldName, $value, $choices);
            
            $elementResourceName = SchemaParser::getResourceNameFromRef($elementProperty);
            if($elementResourceName === 'CodeableConcept'){
                $value = [
                    'coding' => [
                        [
                            'system' => $modifiedElementProperty['systemsByCode'][$value],
                            'code' => $value
                        ]
                    ]
                ];
            }
        }
        else if($modifiedElementProperty['type'] === 'boolean'){
            if($value === 'true' || $value === '1'){
                $value = true;
            }
            else if($value === 'false' || $value === '0'){
                $value = false;
            }
        }
        else if(in_array($ref, ['dateTime', 'instant']) || $modifiedElementProperty['pattern'] === DATE_TIME_PATTERN){
            $value = $this->getModule()->formatFHIRDateTime($value);
        }
        else if($modifiedElementProperty['pattern'] === INTEGER_PATTERN){
            if(ctype_digit($value)){
                $value = (int) $value;
            }
            else{
                throw new \Exception("Expected an integer value for the '$fieldName' field but found '$value' instead.");
            }
        }
        else if($ref === 'decimal'){
            $newValue = (float) $value;
            if((string)$newValue === $value){
                $value = $newValue;
            }
            else{
                throw new \Exception("Expected a decimal value for the '$fieldName' field but found '$value' instead.");
            }
        }

        if($elementProperty['type'] === 'array'){
            $subPath[$elementName][] = $value;
        }
        else{
            $subPath[$elementName] = $value;
        }
    }

    private function processAdditionalElements($mapping, $data){
        $resource = $mapping['type'];
        foreach($mapping['additionalElements'] as $details){
            $fieldName = @$details['field'];
            if($fieldName === null){
                $value = $details['value'];
            }
            else{
                $value = $data[$fieldName];
            }
            
            $this->processElementMapping($fieldName, $value, "$resource/{$details['element']}", false);
        }
    }

    private function getMatchingChoiceValue($projectId, $fieldName, $value, $choices){
        // An example of a case where it makes sense to match the label instead of the value is a REDCap gender value of 'F' with a label of 'Female'.
        $label = strtolower($this->getModule()->getChoiceLabel(['project_id'=>$projectId, 'field_name'=>$fieldName, 'value'=>$value]));
        $possibleValues = [$value, $label];

        $lowerCaseMap = [];
        foreach($choices as $code => $label){
            $lowerCaseMap[strtolower($label)] = $code;
            $lowerCaseMap[strtolower($code)] = $code;
        }

        foreach($possibleValues as $possibleValue){
            // Use lower case strings for case insensitive matching.
            $matchedValue = @$lowerCaseMap[strtolower($possibleValue)];
            if($matchedValue !== null){
                return $matchedValue;
            }
        }

        return $value;
    }
}