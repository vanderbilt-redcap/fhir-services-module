$(function(){
    var module = $.extend(FHIRServicesExternalModule, {
        init: function(){
            var elementTypeahead = module.initTypeahead({placeholder: 'Type or select an Element'})
            var resourceTypeahead = module.initResourceTypeahead(elementTypeahead)

            module.RESOURCE_TYPEAHEAD = resourceTypeahead
            module.ELEMENT_TYPEAHEAD = elementTypeahead

            var typeaheadContainer = $('<div />')
            typeaheadContainer.append(resourceTypeahead)
            typeaheadContainer.append(elementTypeahead)

            var validationTypeSelect = module.getOntologySelect()
            validationTypeSelect.parent().append(typeaheadContainer)

            var ontologySelectValue = 'FHIR-ELEMENT'
            var openAddQuesFormVisible = window.openAddQuesFormVisible
            window.openAddQuesFormVisible = function(){
                openAddQuesFormVisible.apply(null, arguments);

                var details = module.getExistingActionTagDetails()
                
                if(details.value === ''){
                    resourceTypeahead.val('')
                    elementTypeahead.val('')
                    typeaheadContainer.hide()
                    elementTypeahead.hide()
                }
                else{
                    var parts = details.value.split('/')
                    module.getOntologySelect().val(ontologySelectValue)
                    resourceTypeahead.val(parts.shift())
                    elementTypeahead.val(parts.join('/'))

                    typeaheadContainer.show()
                    module.initElementAutocomplete()
                }
            }
            
            validationTypeSelect.append("<option value='" + ontologySelectValue + "'>FHIR Resource/Element</option>")
            validationTypeSelect.change(function(){
                if(validationTypeSelect.val() === ontologySelectValue){
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
                source: [],
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

            typeahead.keypress(function(e) {
                var code = (e.keyCode ? e.keyCode : e.which);
                if(code == 13) { //Enter keycode
                    // Ignore it
                    return false;
                }
            });

            typeahead.autocomplete({
                appendTo: '#div_add_field', // required for z-index to work properly
                source: options.source,
                minLength: 0,
                classes: {
                    'ui-autocomplete': 'fhir-services-module'
                },
                select: function(e, result){
                    typeahead.val(result.item.value)
                    typeahead.blur()
                }
            })
            .autocomplete( "instance" )._renderItem = function( ul, item ){
                var label = item.label

                if(item.description){
                    label = "<b>" + item.label + "</b><br>" + item.description
                }

                return $( "<li />" )
                    .append('<div>' + label + '</div>')
                    .appendTo( ul );
            }

            return typeahead
        },
        initResourceTypeahead: function(elementTypeahead){
            var resourceTypeAhead = module.initTypeahead({
                source: Object.keys(module.schema),
                placeholder: 'Type or select a Resource',
                blur: function(typeahead){
                    var elements = module.getElementsForResource()
                    if(elements){
                        module.initElementAutocomplete()
                        elementTypeahead.focus()
                    }
                    else{
                        elementTypeahead.hide()
                    }
                }
            })

            elementTypeahead.blur(function(){
                var textarea = module.getActionTagTextArea()
                var tags = textarea.val()

                var details = module.getExistingActionTagDetails()
                var tagStartIndex = details.tagStartIndex
                var tagEndIndex = details.tagEndIndex

                var resource = resourceTypeAhead.val()
                var element = elementTypeahead.val()

                var newTag = ''
                if(resource != '' && element != ''){
                    newTag = module.ACTION_TAG_PREFIX + resource + '/' + element + module.ACTION_TAG_SUFFIX
                }

                if(tagStartIndex > 0 && tags[tagStartIndex-1] !== ' '){
                    newTag = ' ' + newTag
                }

                textarea.val(tags.substring(0, tagStartIndex) + newTag + tags.substring(tagEndIndex))
            })

            return resourceTypeAhead
        },
        initElementAutocomplete: function(){
            var elements = module.getElementsForResource()

            var options = []
            for(var path in elements){
                options.push({
                    label: path,
                    value: path,
                    description: elements[path].description
                })
            }

            module.ELEMENT_TYPEAHEAD.autocomplete('option', 'source', options)
            module.ELEMENT_TYPEAHEAD.show()
        },
        getElementsForResource: function(){
            return module.schema[module.RESOURCE_TYPEAHEAD.val()]
        },
        ACTION_TAG_PREFIX: "@FHIR-ELEMENT='",
        ACTION_TAG_SUFFIX: "'",
        getActionTagTextArea: function(){
            return $('#div_field_annotation textarea')
        },
        getExistingActionTagDetails(){
            var textarea = module.getActionTagTextArea()
            var tags = textarea.val()

            var tagPrefix = module.ACTION_TAG_PREFIX
            var tagStartIndex = tags.indexOf(tagPrefix)
            if(tagStartIndex === -1){
                tagStartIndex = tags.length
            }

            var tagSuffix = module.ACTION_TAG_SUFFIX
            var tagEndIndex = tags.indexOf(tagSuffix, tagStartIndex+tagPrefix.length)
            if(tagEndIndex === -1){
                tagEndIndex = tags.length
            }
            else{
                tagEndIndex++ // put it past the end of the tag
            }

            return {
                tagStartIndex: tagStartIndex,
                tagEndIndex: tagEndIndex,
                value: tags.substring(tagStartIndex + tagPrefix.length, tagEndIndex-1)
            }
        }
    })

    module.init()
})