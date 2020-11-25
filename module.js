$(function(){
    var module = $.extend(FHIRServicesExternalModule, {
        init: function(){
            var elementTypeahead = module.initTypeahead({placeholder: 'Type or select an Element'})
            elementTypeahead.hide()

            var resourceTypeahead = module.initResourceTypeahead(elementTypeahead)

            var typeaheadContainer = $('<div />')
            typeaheadContainer.append(resourceTypeahead)
            typeaheadContainer.append(elementTypeahead)
            typeaheadContainer.hide()

            var validationTypeSelect = module.getOntologySelect()
            validationTypeSelect.parent().append(typeaheadContainer)
            
            var fhirElement = 'FHIR-ELEMENT'
            validationTypeSelect.append("<option value='" + fhirElement + "'>FHIR Resource/Element</option>")
            validationTypeSelect.change(function(){
                if(validationTypeSelect.val() === fhirElement){
                    module.initAutocomplete(resourceTypeahead, {
                        source: Object.keys(module.schema)
                    })

                    typeaheadContainer.show()
                    
                    if(resourceTypeahead.val() === ''){
                        resourceTypeahead.focus()
                    }
                }
                else{
                    typeaheadContainer.hide()
                }
            })
        },
        getOntologySelect: function(){
            return $('#ontology_service_select')
        },
        initTypeahead: function(options){
            options = $.extend({
                focus: function(){},
                blur: function(){}
            }, options)

            var typeahead = $('<input class="x-form-text x-form-field" placeholder="' + options.placeholder + '" style="margin-top: 3px">')
            typeahead[0].style.width = module.getOntologySelect()[0].style.width

            typeahead.focus(function(){
                options.focus(typeahead)

                $(function(){
                    typeahead.data("uiAutocomplete").search(typeahead.val());
                })
            })

            typeahead.blur(function(){
                options.blur(typeahead)

                var source = typeahead.autocomplete('option', 'source');
                if(typeof source[0] !== 'string'){
                    source = source.map(function(item){
                        return item.label
                    })
                }

                if(source.indexOf(typeahead.val()) === -1){
                    typeahead.val('')
                }
            })

            return typeahead
        },
        initResourceTypeahead: function(elementTypeahead){
            var resourceTypeAhead = module.initTypeahead({
                placeholder: 'Type or select a Resource',
                blur: function(typeahead){
                    var elements = module.schema[typeahead.val()]
                    if(elements){
                        var options = []
                        for(var path in elements){
                            options.push({
                                label: path,
                                value: path,
                                description: elements[path].description
                            })
                        }

                        module.initAutocomplete(elementTypeahead, {
                            source: options
                        })

                        elementTypeahead.show()
                        elementTypeahead.focus()
                    }
                    else{
                        elementTypeahead.hide()
                    }
                }
            })

            elementTypeahead.blur(function(){
                var resource = resourceTypeAhead.val()
                var element = elementTypeahead.val()

                var textarea = $('#div_parent_field_annotation textarea')
                var tags = textarea.val()

                var tagPrefix = "@FHIR-ELEMENT='"
                var tagStartIndex = tags.indexOf(tagPrefix)
                if(tagStartIndex === -1){
                    tagStartIndex = tags.length
                }

                var tagSuffix = "'"
                var tagEndIndex = tags.indexOf(tagSuffix, tagStartIndex+tagPrefix.length)
                if(tagEndIndex === -1){
                    tagEndIndex = tags.length
                }
                else{
                    tagEndIndex++ // put it past the end of the tag
                }

                var newTag = ''
                if(resource != '' && element != ''){
                    newTag = tagPrefix + resource + '/' + element + tagSuffix
                }

                if(tagStartIndex > 0 && tags[tagStartIndex-1] !== ' '){
                    newTag = ' ' + newTag
                }

                textarea.val(tags.substring(0, tagStartIndex) + newTag + tags.substring(tagEndIndex))
            })

            return resourceTypeAhead
        },
        initAutocomplete: function(typeahead, options){
            options.minLength = 0
            options.select = function(e, result){
                typeahead.val(result.item.value)
                typeahead.blur()
            }
            
            typeahead.autocomplete(options)
            .autocomplete( "instance" )._renderItem = function( ul, item ){
                var label = item.label

                if(item.description){
                    label = "<b>" + item.label + "</b><br>" + item.description
                }

                return $( "<li />" )
                    .append('<div>' + label + '</div>')
                    .appendTo( ul );
            }
        }
    })

    module.init()
})