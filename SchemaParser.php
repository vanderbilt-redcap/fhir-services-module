<?php namespace Vanderbilt\FHIRServicesExternalModule;

use Exception;

class SchemaParser{
    private static $definitions;
    private static $dataElements;
    private static $expansions;
    private static $modifiedSchema;

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
            self::$definitions = json_decode(self::getSchemaJSON(), true)['definitions'];
        }

        return self::$definitions;
    }

    static function getModifiedSchema(){
        if(self::$modifiedSchema === null){
            self::$modifiedSchema = [];

            foreach(self::getDefinitions() as $definition){
                $properties = @$definition['properties'];
                $resourceName = @$properties['resourceType']['const'];
                if(in_array($resourceName, [null])){
                    // Skip definitions that aren't resources.
                    continue;
                }

                self::handleProperties([$resourceName], $properties);    
            }
        }

        return self::$modifiedSchema;
    }

    static function handleProperties($parents, $properties){
        foreach($properties as $propertyName=>$property){
            if(
                // Skip meta-properties
                in_array($propertyName, ['resourceType', 'id', 'meta', 'implicitRules', 'contained', 'extension', 'modifierExtension', 'identifier'])
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

            $refDefinitionName = self::getResourceNameFromRef($property);
            $subProperties = @self::$definitions[$refDefinitionName]['properties'];
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
                    self::handleProperties($parts, $subProperties);
                }
            }
        }
    }

    static function getResourceNameFromRef($property){
        $items = @$property['items'];
        if($items !== null){
            $ref = @$items['$ref'];
        }
        else{
            $ref = @$property['$ref'];
        }
        
        return @explode('/', $ref)[2];
    }

    private static function handleProperty($parts, $property){
        $enum = @$property['enum'];
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
        return self::getModifiedSchema()[$resourceName][$elementPath];
    }

    private static function addCodeableConceptValues($pathParts, &$property){
        if($pathParts === ['Observation', 'code']){
            // The LOINC code list is way too long.  Just have users manually enter LOINC codes.
            $property['description'] = "The LOINC code for this Observation.  Visit <a target='_blank' href='https://search.loinc.org/' style='color: #000066'>search.loinc.org</a> to search for valid LOINC codes.";
            return;
        }

        $dataElement = self::getDataElements()[implode('.', $pathParts)];
        $valueSetUrl = $dataElement->snapshot->element[0]->binding->valueSet;

        $expansion = self::getExpansions()[$valueSetUrl];

        $choices = [];
        foreach($expansion->expansion->contains as $option){
            $code = $option->code;
            $choices[$code] = $option->display;
            $property['systemsByCode'][$code] = $option->system;
        }

        
        $property['redcapChoices'] = $choices;
    }

    private static function getDataElements(){
        if(self::$dataElements === null){
            $elements = json_decode(self::getFhirJSON('dataelements.json'))->entry;
            foreach($elements as $element){
                $element = $element->resource;
                self::$dataElements[$element->name] = $element;
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
