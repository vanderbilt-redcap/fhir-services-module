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

        $assertDiff = function($one, $two, $message) use ($actual){
            $diffs = [];
            foreach($one as $resourceName => $onePaths){
                $twoPaths = $two[$resourceName];
                $diff = array_diff($onePaths, $twoPaths);
                if(!empty($diff)){
                    $diffs[$resourceName] = $diff;
                }
            }
    
            if(!empty($diffs)){
                $actualPath = __DIR__ . '/actual-modified-schema-summary.json';
                file_put_contents($actualPath, json_encode($actual, JSON_PRETTY_PRINT));
                
                $count = 0;
                foreach($diffs as $resourceName => $elementPaths){
                    foreach($elementPaths as $elementPath){
                        // Create lines for each grepping to verify changes.
                        echo "modified-schema-diff: $resourceName/$elementPath\n";
                        $count++;
                    }
                }

                throw new Exception("The previously displayed $count modified schema paths were $message");
            }
        };

        $expected = json_decode(file_get_contents(__DIR__ . '/expected-modified-schema-summary.json'), true);
        if($actual !== $expected){
            $assertDiff($expected, $actual, "removed!  Please review the difference between the expected & actual modified schema summary files.");

            $assertDiff($actual, $expected, "added!  If this was intentional, you may want to update the expected modified scheme summary with the actual summary.");
        }

        // Every test needs an assertion, even though the equals check above should already cover this.
        $this->assertSame($expected, $actual);
    }
}