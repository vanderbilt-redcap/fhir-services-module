<?php namespace Vanderbilt\FHIRServicesExternalModule;

class SchemaParser{
    private static $definitions;
    private static $result;

    static function parse(){
        self::$result = [];

        $schema = json_decode(file_get_contents(__DIR__ . '/fhir.schema.json'), true);
        
        self::$definitions = $schema['definitions'];
        foreach(self::$definitions as $definition){
            $properties = @$definition['properties'];
            $resourceName = @$properties['resourceType']['const'];
            if(in_array($resourceName, [null])){
                // Skip definitions that aren't resources.
                continue;
            }

            self::handleProperties([$resourceName], $properties);    
        }

        return self::$result;
    }

    static function handleProperties($parents, $properties){
        foreach($properties as $propertyName=>$property){
            if(
                // Skip meta-properties
                in_array($propertyName, ['resourceType', 'id', 'meta', 'implicitRules', 'language', 'text', 'contained', 'extension', 'modifierExtension', 'identifier'])
                ||
                // Ignore recursive loops
                in_array($propertyName, $parents)
                ||
                // Are these related to extensions?
                $propertyName[0] === '_'
            ){
                continue;
            }

            $items = @$property['items'];
            if($items !== null){
                $ref = @$items['$ref'];
            }
            else{
                $ref = @$property['$ref'];
            }

            $refDefinitionName = @explode('/', $ref)[2];
            $subProperties = @self::$definitions[$refDefinitionName]['properties'];
            $parts = array_merge($parents, [$propertyName]);
            
            if($subProperties === null){
                self::handleProperty($parts, $property);
            }
            else{
                if($refDefinitionName === 'ContactPoint'){
                    $systemCodes = $subProperties['system']['enum'];
                    unset($subProperties['system']);
                    foreach($systemCodes as $code){
                        self::handleProperties(array_merge($parts, [$code]), $subProperties);
                    }
                }
                else{
                    self::handleProperties($parts, $subProperties);
                }
            }
        }
    }

    private static function handleProperty($parts, $property){
        $resourceName = array_shift($parts);
        self::$result[$resourceName][implode('/', $parts)] = $property;
    }
}
