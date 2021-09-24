<?php namespace Vanderbilt\FHIRServicesExternalModule;

use Exception;
use Throwable;

class SchemaParser{
    private static $definitions;
    private static $dataElements;
    private static $expansions;
    private static $modifiedSchema;
    private static $targetProfiles;
    private static $codesBySystem;

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

    static function getCodesBySystem(){
        return static::$codesBySystem;
    }

    static function getTargetProfiles(){
        if(self::$targetProfiles === null){
            self::getModifiedSchema();
        }

        return self::$targetProfiles;
    }

    static function isRecursiveLoop($parents, $propertyName){
        /**
         * This was an easy way to prevent recursive loops.
         * We may want to transition to a smart solution in the future,
         * perhaps one that takes into account types instead of just paths/names.
         * On the other hand, having a manual exception list here might be a good next step,
         * until we notice a pattern we can automate in the list of exceptions.
         */

        $parts = array_merge($parents, [$propertyName]);
        $parts = array_splice($parts, -3, 3);

        return
            !(
                $parts === ['code', 'coding', 'code']
                ||
                $parts === ['useContext', 'code', 'code']
            )
            &&
            // We should likely modify this to match types instead of just the property name.
            // On second thought, that would open us back up to recursive loops wouldn't it...
            in_array($propertyName, $parents)
        ;
    }

    static function handleProperties($parents, $parentProperty, $properties){
        foreach($properties as $propertyName=>$property){
            if(
                // Skip meta-properties
                in_array($propertyName, ['resourceType', 'id', 'meta', 'implicitRules', 'contained', 'modifierExtension', 'identifier'])
                ||
                ($propertyName === 'extension' && ($property['added-by-this-module'] ?? null) !== true)
                ||
                self::isRecursiveLoop($parents, $propertyName)
                ||
                // Are these related to extensions?
                $propertyName[0] === '_'
            ){
                continue;
            }

            $property['description'] = $propertyName . ' - ' . ($property['description'] ?? '');
            if($parentProperty !== null){
                $property['description'] = $parentProperty['description'] . "\n\n" . $property['description'];
            }

            $refDefinitionName = self::getResourceNameFromRef($property);
            $property['resourceName'] = $refDefinitionName;
            $property['parentResourceName'] = $parentProperty['resourceName'] ?? null;

            $subProperties = self::getDefinitions()[$refDefinitionName]['properties'] ?? null;
            $parts = array_merge($parents, [$propertyName]);

            if($subProperties === null){
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
        if($property['parentResourceName'] === 'Coding'){
            $lastPart = array_slice($parts, -1, )[0];
            if($lastPart === 'system'){
                // Exclude Coding/system, since it's handled differently in the mapping UI.
                return;
            }
            else if($lastPart === 'code'){
                self::addCodingValues($parts, $property);
            }
        }

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

    private static function addCodingValues($pathParts, &$property){
        array_pop($pathParts);  // Remove 'code'
        if(end($pathParts) === 'coding'){
            // This is required to make the UI function for direct coding references
            // like Encounter/class, as opposed to Encounter/type.
            array_pop($pathParts);  // Remove 'coding'
        }

        $dataElement = self::getDataElements()[implode('.', $pathParts)] ?? null;
        $parts = explode('|', $dataElement->snapshot->element[0]->binding->valueSet ?? null); // trim off the version string
        $valueSetUrl = $parts[0];

        $expansion = self::getExpansions()[$valueSetUrl] ?? null;

        $options = $expansion->expansion->contains ?? [];

        $system = $options[0]->system ?? null;
        $property['system'] = $system;

        $choices = [];
        if(
            /**
             * There are a few SNOMED value sets greater than 1000 that don't say they're truncated,
             * but I think they might still be. 
             */
            count($options) >= 1000
            && in_array($system, [
                'http://snomed.info/sct',
                'http://loinc.org',
                'http://unitsofmeasure.org'
            ])
        ){
            /**
             * The list of options us not available since it is very large.
             * Do not include any choices in this case so users can enter whatever value they like.
             */
        }
        else{
            foreach($options as $option){
                if($system !== $option->system){
                    // The UI only supports one system for now.  Only show choices for the first one.
                    break;
                }

                $code = $option->code;
                $label = $option->display ?? null;

                $choices[$code] = $label;
                static::$codesBySystem[$system][$code] = $label;
            }
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
