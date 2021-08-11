<?php namespace Vanderbilt\FHIRServicesExternalModule;

use REDCap;
use Exception;

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
        $rows = json_decode(REDCap::getData($projectId, 'json', $recordId, $fields), true);
        foreach($rows as $data){
            foreach($data as $fieldName=>$value){
                $mapping = $mappings[$fieldName] ?? null;
                if($value === '' || $mapping === null){
                    continue;
                }

                $isArrayMapping = is_array($mapping);
                if($isArrayMapping){
                    $primaryMapping = $mapping['type'] . '/' . $mapping['primaryElementPath'];
                }
                else{
                    $primaryMapping = $mapping;
                }

                $this->processElementMapping($data, $fieldName, $value, $primaryMapping, $isArrayMapping);

                if($isArrayMapping){
                    $this->processAdditionalElements($mapping, $data);
                }
            }
        }

        $this->processExtensions();
    }

    private function processExtensions(){
        foreach($this->resources as $type=>&$resources){
            foreach($resources as &$resource){
                if($type === 'Patient'){
                    foreach(['race', 'ethnicity'] as $extensionName){
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

    private function getNewArrayItemParents($resourceName, $elementParents){
        $definitions = SchemaParser::getDefinitions();
        $lastDefinition = $definitions[$resourceName];

        $lastArray = 0;
        $newArrayItemParents = [];
        foreach($elementParents as $currentName){
            $newArrayItemParents[] = $currentName;
            $current = $lastDefinition['properties'][$currentName];
            if($current['type'] === 'array'){
                $lastArray = count($newArrayItemParents);
            }

            $subResourceName = SchemaParser::getResourceNameFromRef($current);
            $lastDefinition = $definitions[$subResourceName];
        }
        
        return array_slice($newArrayItemParents, 0, $lastArray);
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

    private function processElementMapping($data, $fieldName, $value, $mappingString, $addNewArrayItem){
        $parts = explode('/', $mappingString);
        $resourceName = array_shift($parts);
        $elementPath = implode('/', $parts);
        $elementName = array_pop($parts);
        $elementParents = $parts;

        if(empty($resourceName)){
            throw new Exception('Mapping is missing the resource type!');
        }

        if($addNewArrayItem){
            $newArrayItemParents = $this->getNewArrayItemParents($resourceName, $elementParents);
        }
        else{
            $newArrayItemParents = null;
        }

        $definitions = SchemaParser::getDefinitions();

        $resource = &$this->getArrayChild($this->resources[$resourceName], $addNewArrayItem && $this->getModule()->isRepeatableResource($resourceName));
        if(!isset($resource['id'])){
            $id = $this->getModule()->getResourceId($resourceName, $this->getProjectId(), $this->getRecordId(), $fieldName, $data);
            $resource = [
                'resourceType' => $resourceName,
                'id' => $id
            ];

            $resource['identifier'][] = [
                'system' => $this->getModule()->getResourceUrlPrefix(),
                'value' => $this->getModule()->getRelativeResourceUrl($resource)
            ];
        }

        $subPath = &$resource;
        $subResourceName = $resourceName;
        $parentProperty = [
            'type' => null
        ];
        $parentDefinition = $definitions[$resourceName];

        $parentsSoFar = [];
        foreach($elementParents as $parentName){
            $subPath = &$subPath[$parentName];
            $parentsSoFar[] = $parentName;
            $parentProperty = $parentDefinition['properties'][$parentName] ?? null;
            if($parentProperty === null){
                throw new Exception("Property named '$parentName' not found in element path for the '$subResourceName' resource: " . json_encode($parentsSoFar, JSON_PRETTY_PRINT));
            }
            
            $subResourceName = SchemaParser::getResourceNameFromRef($parentProperty);
            $parentDefinition = $definitions[$subResourceName] ?? null;

            if(($parentProperty['type'] ?? null) === 'array'){
                $addNewIfExists = $parentsSoFar === $newArrayItemParents;
                $subPath = &$this->getArrayChild($subPath, $addNewIfExists);
            }
        }

        $elementProperty = $parentDefinition['properties'][$elementName] ?? null;
        if($elementProperty === null){
            throw new Exception("The following mapping is not valid: $mappingString");
        }

        if(($elementProperty['type'] ?? null) !== 'array' && isset($subPath[$elementName])){
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
                throw new StackFreeException("The '$mappingString' element is currently mapped to multiple fields in the same context, but should only be mapped to a single field.  It is recommended to view the Codebook and search for '$mappingString' to determine which field mapping(s) need to be modified.");
            }
        }

        // The java FHIR validator does not allow leading or trailing whitespace.
        $value = trim($value);
        if($value === ''){
            // In FHIR, empty values should just not be specified in the first place.
            return;
        }

        $modifiedElementProperty = SchemaParser::getModifiedProperty($resourceName, $elementPath);
        $choices = $modifiedElementProperty['redcapChoices'] ?? null;
        if($choices !== null){
            $value = $this->getMatchingChoiceValue($this->getProjectId(), $fieldName, $value, $choices);
        }
        
        $ref = SchemaParser::getResourceNameFromRef($modifiedElementProperty);
        $elementResourceName = SchemaParser::getResourceNameFromRef($elementProperty);
        if($elementResourceName === 'CodeableConcept'){
            if($resourceName === 'Observation'){
                $system = 'http://loinc.org';
            }
            else{
                $system = $modifiedElementProperty['systemsByCode'][$value];
            }

            $value = [
                'coding' => [
                    [
                        'system' => $system,
                        'code' => (string) $value // Must be a string for validation to pass
                    ]
                ]
            ];
        }
        else if(in_array('boolean', [$ref, $modifiedElementProperty['type'] ?? null])){
            if($value === 'true' || $value === '1'){
                $value = true;
            }
            else if($value === 'false' || $value === '0'){
                $value = false;
            }
        }
        else if(in_array($ref, ['dateTime', 'instant']) || ($modifiedElementProperty['pattern'] ?? null) === DATE_TIME_PATTERN){
            $value = $this->getModule()->formatFHIRDateTime($value);
        }
        else if(
            ($modifiedElementProperty['pattern'] ?? null) === INTEGER_PATTERN
            ||
            $ref === 'positiveInt'
        ){
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

        if(($elementProperty['type'] ?? null) === 'array'){
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
            
            $this->processElementMapping($data, $fieldName, $value, "$resource/{$details['element']}", false);
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