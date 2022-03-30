{
    "resourceType": "Questionnaire",
    "id": "<?=$pid?>.all-field-type-examples",
    "item": [
        {
            "item": [
                {
                    "linkId": "response_id",
                    "text": "Response ID",
                    "type": "string"
                },
                {
                    "linkId": "text",
                    "required": true,
                    "text": "Basic Text",
                    "type": "string"
                },
                {
                    "linkId": "date",
                    "text": "Date",
                    "type": "date"
                },
                {
                    "linkId": "datetime",
                    "text": "Datetime",
                    "type": "dateTime"
                },
                {
                    "linkId": "integer",
                    "text": "Integer",
                    "type": "integer"
                },
                {
                    "linkId": "decimal",
                    "text": "Decimal",
                    "type": "decimal"
                },
                {
                    "linkId": "time",
                    "text": "Time",
                    "type": "time"
                },
                {
                    "linkId": "notes",
                    "text": "Notes",
                    "type": "text"
                },
                {
                    "answerOption": [
                        {
                            "valueCoding": {
                                "code": "1",
                                "display": "a",
                                "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.dropdown"
                            }
                        },
                        {
                            "valueCoding": {
                                "code": "2",
                                "display": "b",
                                "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.dropdown"
                            }
                        },
                        {
                            "valueCoding": {
                                "code": "3",
                                "display": "c",
                                "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.dropdown"
                            }
                        }
                    ],
                    "linkId": "dropdown",
                    "text": "Dropdown",
                    "type": "choice"
                },
                {
                    "item": [
                        {
                            "answerOption": [
                                {
                                    "valueCoding": {
                                        "code": "1",
                                        "display": "d",
                                        "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.radio"
                                    }
                                },
                                {
                                    "valueCoding": {
                                        "code": "2",
                                        "display": "e",
                                        "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.radio"
                                    }
                                },
                                {
                                    "valueCoding": {
                                        "code": "3",
                                        "display": "f",
                                        "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.radio"
                                    }
                                }
                            ],
                            "linkId": "radio",
                            "text": "Radio",
                            "type": "choice"
                        },
                        {
                            "linkId": "checkboxes___1",
                            "text": "Checkboxes - a",
                            "type": "boolean"
                        },
                        {
                            "linkId": "checkboxes___2",
                            "text": "Checkboxes - b",
                            "type": "boolean"
                        },
                        {
                            "linkId": "checkboxes___3",
                            "text": "Checkboxes - c",
                            "type": "boolean"
                        },
                        {
                            "answerOption": [
                                {
                                    "valueCoding": {
                                        "code": "1",
                                        "display": "Yes",
                                        "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.yes-or-no"
                                    }
                                },
                                {
                                    "valueCoding": {
                                        "code": "0",
                                        "display": "No",
                                        "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.yes-or-no"
                                    }
                                }
                            ],
                            "linkId": "yes_or_no",
                            "text": "Yes or No",
                            "type": "choice"
                        },
                        {
                            "answerOption": [
                                {
                                    "valueCoding": {
                                        "code": "1",
                                        "display": "True",
                                        "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.true-or-false"
                                    }
                                },
                                {
                                    "valueCoding": {
                                        "code": "0",
                                        "display": "False",
                                        "system": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/CodeSystem/<?=$pid?>.true-or-false"
                                    }
                                }
                            ],
                            "linkId": "true_or_false",
                            "text": "True or False",
                            "type": "choice"
                        }
                    ],
                    "linkId": "radio___section_header",
                    "text": "Section Example One",
                    "type": "group"
                },
                {
                    "item": [
                        {
                            "linkId": "file",
                            "text": "File",
                            "type": "attachment"
                        },
                        {
                            "linkId": "descriptive",
                            "text": "This is a descriptive field.",
                            "type": "display"
                        },
                        {
                            "linkId": "descriptive_file",
                            "text": "This is a descriptive field with a file.",
                            "type": "display"
                        }
                    ],
                    "linkId": "file___section_header",
                    "text": "Section Example Two",
                    "type": "group"
                }
            ],
            "linkId": "<?=$pid?>.all-field-type-examples",
            "repeats": false,
            "type": "group"
        }
    ],
    "name": "<?=$pid?>.all-field-type-examples",
    "status": "draft",
    "title": "All Field Type Examples",
    "url": "<?=APP_PATH_WEBROOT_FULL?>api/?type=module&prefix=fhir_services&page=service&NOAUTH&fhir-url=/Questionnaire/<?=$pid?>.all-field-type-examples&canonical"
}