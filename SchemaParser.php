<?php namespace Vanderbilt\FHIRServicesExternalModule;

use Exception;
use Throwable;

class SchemaParser{
    private static $definitions;
    private static $dataElements;
    private static $expansions;
    private static $modifiedSchema;
    private static $targetProfiles;

    private static function getFhirJSON($filename){
        $path = __DIR__ . "/fhir/4.0.1/$filename";
        if(!file_exists($path)){
            throw new Exception("File not found: $path");
        }

        return file_get_contents($path);
    }

    static function getSchemaJSON(){
        return self::getFhirJSON('fhir.schema.json');
    }

    static function getDefinitions(){
        if(self::$definitions === null){
            $definitions = json_decode(self::getSchemaJSON(), true)['definitions'];
            self::applyExtensions($definitions);
            self::$definitions = $definitions;
        }

        return self::$definitions;
    }

    private static function applyExtensions(&$definitions){
        $patientExtension = 'PatientExtension';
        $patientExtensionRace = "{$patientExtension}Race";
        $patientExtensionEthnicity = "{$patientExtension}Ethnicity";

        $definitions[$patientExtension] = [
            'properties' => [
                'race' => [
                    '$ref' => "#/definitions/$patientExtensionRace"
                ],
                'ethnicity' => [
                    '$ref' => "#/definitions/$patientExtensionEthnicity"
                ],
                'birthsex' => [
                    '$ref' => "#/definitions/string"
                ],
            ],
        ];

        foreach([$patientExtensionRace, $patientExtensionEthnicity] as $name){
            $definitions[$name] = [
                'properties' => [
                    'ombCategory' => [
                        'items' => [
                            '$ref' => "#/definitions/string"
                        ],
                        'type' => 'array'
                    ],
                    'detailed' => [
                        'items' => [
                            '$ref' => "#/definitions/string"
                        ],
                        'type' => 'array'
                    ],
                    'text' => [
                        '$ref' => "#/definitions/string"
                    ]
                ],
            ];
        }
        
        $definitions['Patient']['properties']['extension'] = [
            '$ref' => "#/definitions/$patientExtension",
            'added-by-this-module' => true
        ];
    }

    static function getModifiedSchema(){
        if(self::$modifiedSchema === null){
            self::$modifiedSchema = [];
            self::$targetProfiles = [];

            foreach(self::getDefinitions() as $definition){
                $properties = $definition['properties'] ?? null;
                $resourceName = $properties['resourceType']['const'] ?? null;
                if(in_array($resourceName, [null])){
                    // Skip definitions that aren't resources.
                    continue;
                }

                self::handleProperties([$resourceName], null, $properties);    
            }
        }

        return self::$modifiedSchema;
    }

    static function getTargetProfiles(){
        if(self::$targetProfiles === null){
            self::getModifiedSchema();
        }

        return self::$targetProfiles;
    }

    static function handleProperties($parents, $parentProperty, $properties){
        foreach($properties as $propertyName=>$property){
            if(
                // Skip meta-properties
                in_array($propertyName, ['resourceType', 'id', 'meta', 'implicitRules', 'contained', 'modifierExtension', 'identifier'])
                ||
                ($propertyName === 'extension' && ($property['added-by-this-module'] ?? null) !== true)
                ||
                // Ignore recursive loops
                // This currently falsely matches things like code/coding/code.
                // We should modify this to match types instead of just the property name.
                in_array($propertyName, $parents)
                ||
                // Are these related to extensions?
                $propertyName[0] === '_'
            ){
                continue;
            }

            $property['description'] = $propertyName . ' - ' . $property['description'];
            if($parentProperty !== null){
                $property['description'] = $parentProperty['description'] . "\n\n" . $property['description'];
            }

            $refDefinitionName = self::getResourceNameFromRef($property);
            $subProperties = self::getDefinitions()[$refDefinitionName]['properties'] ?? null;
            $parts = array_merge($parents, [$propertyName]);

            if($subProperties === null){
                self::handleProperty($parts, $property);
            }
            else{
                if($refDefinitionName === 'CodeableConcept'){
                    self::addCodeableConceptValues($parts, $property);
                    self::handleProperty($parts, $property);
                }
                else{
                    if($refDefinitionName === 'Reference'){
                        self::indexReference($parts);

                        // Ignore all sub-properties except display.
                        $subProperties = [
                            'display' => $subProperties['display']
                        ];
                    }

                    self::handleProperties($parts, $property, $subProperties);
                }
            }
        }
    }

    private static function getLeafDataElement($pathParts){
        $dataElements = self::getDataElements();

        $path = implode('.', $pathParts);
        $element = $dataElements[$path] ?? null;

        if($element === null){
            if(count($pathParts) === 2){
                // We can't dig any deeper.
                return null;
            }

            $lastPart = array_pop($pathParts);
            $element = self::getLeafDataElement($pathParts);
            if($element === null){
                return null;
            }

            $types = self::getDataElementTypes($element);
            foreach($types as $type){
                $element = $dataElements[$type->code . '.' . $lastPart] ?? null;
                if($element !== null){
                    // Don't check any other types.
                    break;
                }
            }
        }

        return $element;
    }

    private static function getDataElementTypes($dataElement){
        $elements = $dataElement->snapshot->element;
        if(count($elements) !== 1){
            throw new Exception("Unexpected number of elements: " . count($elements));
        }

        return $elements[0]->type;
    }

    private static function getDataElementType($pathParts, $typeString){
        try{
            $dataElement = self::getLeafDataElement($pathParts);
            if($dataElement === null){
                return null;
            }

            foreach(self::getDataElementTypes($dataElement) as $type){
                if($type->code === $typeString){
                    return $type;
                }
            };
        }
        catch(Throwable $t){
            $path = implode('.', $pathParts);
            throw new Exception("Wrapped Exception for path: $path", 0, $t);
        }

        throw new Exception("Could not find the $typeString type in $path");
    }

    private static function indexReference($pathParts){
        $type = self::getDataElementType($pathParts, 'Reference');
        if($type === null){
            /**
             * Some reference relationships cannot be detected currently.
             * They seem to be limited to the ones that have a little chain icon
             * in the FHIR docs, like Contract/term/group.
             */
            return;
        }

        $pathResource = array_shift($pathParts);
        $elementPath = implode('/', $pathParts);
        $lastPart = $pathParts[count($pathParts)-1];
        
        foreach(($type->targetProfile ?? []) as $profile){
            $profileResource = explode('http://hl7.org/fhir/StructureDefinition/', $profile)[1];

            if(
                ($profileResource === 'Patient' && in_array($lastPart, ['subject', 'patient', 'individual']))
                ||
                ($profileResource === 'ResearchStudy' && in_array($lastPart, ['study']))
            ){
                if(count($pathParts) > 1){
                    throw new Exception("References with multiple path parts are not yet implemented (though support should be very easy to add): $pathResource/$elementPath");
                }

                $existingPath = self::$targetProfiles[$profileResource][$pathResource] ?? null;
                if($existingPath !== null){
                    throw new Exception("Tried to set a path of $elementPath for $pathResource, but $existingPath was already set.");
                }

                self::$targetProfiles[$profileResource][$pathResource] = $elementPath;
            }
        }
    }

    static function getResourceNameFromRef($property){
        $items = $property['items'] ?? null;
        if($items !== null){
            $ref = $items['$ref'] ?? null;
        }
        else{
            $ref = $property['$ref'] ?? null;
        }
        
        return explode('/', $ref)[2] ?? null;
    }

    private static function handleProperty($parts, $property){
        $enum = $property['enum'] ?? null;
        if($enum){
            $choices = [];
            foreach($enum as $value){
                $choices[$value] = ucfirst($value);
            }

            $property['redcapChoices'] = $choices;
        }

        $resourceName = array_shift($parts);
        self::$modifiedSchema[$resourceName][implode('/', $parts)] = $property;
    }

    static function getModifiedProperty($resourceName, $elementPath){
        return self::getModifiedSchema()[$resourceName][$elementPath] ?? null;
    }

    private static function addCodeableConceptValues($pathParts, &$property){
        if($pathParts === ['Observation', 'code']){
            // The LOINC code list is way too long.  Just have users manually enter LOINC codes.
            $property['description'] = "The LOINC code for this Observation.  Visit <a target='_blank' href='https://search.loinc.org/' style='color: #000066'>search.loinc.org</a> to search for valid LOINC codes.";
            return;
        }

        $dataElement = self::getDataElements()[implode('.', $pathParts)] ?? null;
        $parts = explode('|', $dataElement->snapshot->element[0]->binding->valueSet ?? null); // trim off the version string
        $valueSetUrl = $parts[0];

        $expansion = self::getExpansions()[$valueSetUrl] ?? null;

        $choices = [];
        foreach(($expansion->expansion->contains ?? []) as $option){
            $code = $option->code;
            $choices[$code] = $option->display ?? null;
            $property['systemsByCode'][$code] = $option->system;
        }

        $property['redcapChoices'] = $choices;
    }

    private static function getDataElements(){
        if(self::$dataElements === null){
            $elements = json_decode(self::getFhirJSON('dataelements.json'))->entry;
            foreach($elements as $element){
                $element = $element->resource;
                $name = $element->name;
                $parts = explode('[x]', $name);

                if(count($parts) === 2){
                    $subElements = $element->snapshot->element;
                    if(count($subElements) !== 1){
                        throw new Exception("Unexpected number of elements for $name: " . count($subElements));
                    }

                    foreach($subElements[0]->type as $type){
                        self::$dataElements[$parts[0] . $type->code] = $element;
                    }
                }
                else{
                    self::$dataElements[$name] = $element;
                }
            }
        }

        return self::$dataElements;
    }

    private static function getExpansions(){
        if(self::$expansions === null){
            $expansions = json_decode(self::getFhirJSON('expansions.json'))->entry;
            foreach($expansions as $expansion){
                $expansion = $expansion->resource;
                self::$expansions[$expansion->url] = $expansion;
            }
        }

        return self::$expansions;
    }
}
