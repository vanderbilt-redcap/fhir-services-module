<?php namespace Vanderbilt\FHIRServicesExternalModule;

use Exception;

class SchemaParserTest extends BaseTest{
    function testGetModifiedSchema(){
        $actual = [];
        foreach(SchemaParser::getModifiedSchema() as $resourceName => $elementPaths){
            foreach($elementPaths as $elementPath=>$details){
                $actual[$resourceName][] = $elementPath;
            }
        }

        $handleError = function($message) use ($actual){    
            $actualPath = __DIR__ . '/actual-modified-schema-summary.json';
            file_put_contents($actualPath, json_encode($actual, JSON_PRETTY_PRINT));
            throw new Exception($message);
        };

        $expected = json_decode(file_get_contents(__DIR__ . '/expected-modified-schema-summary.json'), true);
        if($actual !== $expected){
            foreach($expected as $resourceName => $expectedPaths){
                $actualPaths = $actual[$resourceName];
                if(!empty(array_diff($expectedPaths, $actualPaths))){
                    $handleError("Modified schema paths were removed!  Please review the difference between the expected & actual modified schema summary files.");
                }
            }

            $handleError("Modified schema paths were added!  If this was intentional, you may want to update the expected modified scheme summary with the actual summary.");
        }

        // Every test needs an assertion, even though the equals check above should already cover this.
        $this->assertSame($expected, $actual);
    }
}