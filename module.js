$(function(){
    var module = $.extend(FHIRServicesExternalModule, {
        RECOMMENDED_CHOICES_LINK: $("<a href='#' class='fhir-services-recommended-choices-link'>View the recommended choices for this element</a>"),
        RECOMMENDED_CHOICES_DIALOG_ID: 'fhir-services-invalid-choices-dialog',
        FIELD: 'field',
        VALUE: 'value',
        init: function(){
            var elementTypeahead = module.initTypeahead({
                change: () => {
                    let mapping
                    if(elementTypeahead.val() === ''){
                        mapping = {}
                    }
                    else{
                        // Prevent the current additional elements from being changed.
                        mapping = undefined
                    }

                    module.updateAdditionalElementVisibility(mapping)
                }
            })
            
            var resourceTypeahead = module.initResourceTypeahead(elementTypeahead)

            module.RESOURCE_TYPEAHEAD = resourceTypeahead
            module.ELEMENT_TYPEAHEAD = elementTypeahead

            var typeaheadContainer = $('<div id="fhir-services-mapping-field-settings" style="border: 1px solid rgb(211, 211, 211); padding: 4px 8px; margin-top: 5px; display: block;"><b>FHIR Mapping</b></div>')
            typeaheadContainer.append(module.createTable({
                'Resource': resourceTypeahead,
                'Element': elementTypeahead
            }))

            typeaheadContainer.append(module.RECOMMENDED_CHOICES_LINK)

            module.ADDITIONAL_ELEMENT_CONTAINER = $(`
                <div>
                    <b class='fhir-services-additional-element-header'>Additional Elements</b>
                    <div id='fhir-services-additional-elements'></div>
                    <div id='fhir-services-additional-element-buttons'></div>
                </div>
            `)

            module.addAdditionalElementButton(module.FIELD)
            module.addAdditionalElementButton(module.VALUE)

            typeaheadContainer.append(module.ADDITIONAL_ELEMENT_CONTAINER)
            
            var openAddQuesFormVisible = window.openAddQuesFormVisible
            window.openAddQuesFormVisible = function(){
                openAddQuesFormVisible.apply(null, arguments);

                let details = module.getExistingActionTagDetails()
                if(!details){
                    // A error must have occurred.
                    return
                }
                
                let mapping
                if(details.value === ''){
                    /**
                     * The previously selected resource is intentionally left in place to make it
                     * easy to map several fields for the same resource in a row.
                     */
                    elementTypeahead.val('')
                    module.hideElementTypeahead()
                    mapping = {}
                }
                else{
                    mapping = module.parseMapping(details.value)

                    resourceTypeahead.val(mapping.type)
                    elementTypeahead.val(mapping.primaryElementPath)

                    module.initElementAutocomplete(elementTypeahead, true)
                    module.showElementTypeahead()
                }
                
                module.updateRecommendedChoicesVisibility()
                module.updateAdditionalElementVisibility(mapping)

                if(resourceTypeahead.val() !== ''){
                    module.showElementTypeahead()
                }
            }

            $('#div_field_req').before(typeaheadContainer)

            module.initSaveButton()
            module.initRecommendedChoiceLinks()
        },
        parseMapping: (actionTagValue) => {
            if(actionTagValue[0] === '{'){
                return module.actionTagDecode(actionTagValue);
            }
            else{
                const parts = actionTagValue.split('/')
                return {
                    type: parts.shift(),
                    primaryElementPath: parts.join('/'),
                }
            }
        },
        actionTagDecode: (value) => {
            value = value.replaceAll(module.SINGLE_QUOTE_PLACEHOLDER, module.ACTION_TAG_SUFFIX);
            return JSON.parse(value)
        },
        actionTagEncode: (value) => {
            value = JSON.stringify(value, null, 2)
            return value.replaceAll(module.ACTION_TAG_SUFFIX, module.SINGLE_QUOTE_PLACEHOLDER);
        },
        hideElementTypeahead: () => {
            module.ELEMENT_TYPEAHEAD.closest('tr').hide()
        },
        showElementTypeahead: () => {
            module.ELEMENT_TYPEAHEAD.closest('tr').show()
        },
        createTable: (rows) => {
            let table = $('<table></table>')

            for(let label in rows){
                let row = $('<tr></tr>')

                let addColumn = function(content){
                    let column = $('<td></td>')
                    column.append(content)
                    row.append(column)
                }

                addColumn('<label>' + label + '</label>')
                addColumn(rows[label])

                table.append(row)
            }

            let wrapper = $('<div class="fhir-services-table-wrapper" />')
            wrapper.append(table)

            return wrapper
        },
        initSaveButton: function(){
            var addEditFieldSave = window.addEditFieldSave
            var finishSave = function(){
                addEditFieldSave.apply(null, arguments);
            }

            window.addEditFieldSave = function(){
                var element = module.getMappedElement()
                if(element === undefined || element.redcapChoices === undefined){
                    // An element with choices is not mapped
                    finishSave()
                    return
                }

                var validValues = {}
                for(var code in element.redcapChoices){
                    var value = element.redcapChoices[code]
                    ;[code, value].forEach(function(codeOrValue){
                        validValues[codeOrValue.toLowerCase()] = true
                    })
                }

                var invalidChoices = []

                var existingChoices = $('#element_enum').val().trim()
                if(existingChoices === ''){
                    $('#element_enum').val(module.getRecommendedChoices())
                }
                else{
                    existingChoices.split("\n").forEach(function(line){
                        line = line.trim()
                        if(line === ''){
                            return
                        }
    
                        var separator = ', '
                        var separatorIndex = line.indexOf(separator)
                        var value = line.substring(0, separatorIndex).toLowerCase()
                        var label = line.substring(separatorIndex+separator.length).toLowerCase()
    
                        if(validValues[value] === undefined && validValues[label] === undefined){
                            invalidChoices.push(line)
                        }
                    })
                }

                if(invalidChoices.length === 0){
                    finishSave()
                    return
                }
                
                simpleDialog(`
                    <div>
                        The following choices are not valid for the currently mapped FHIR element.
                        They must be modified to ensure FHIR compatibility as described at the top of the <a href='#' class='fhir-services-recommended-choices-link'>list of recommended choices</a>:
                    </div>
                    <ul>
                        <li>` + invalidChoices.join('</li><li>') + `</li>
                    </ul>
                `, 'Invalid Choices Exist', module.RECOMMENDED_CHOICES_DIALOG_ID)
            }
        },
        getMappedElement: function(){
            const elements = module.getElementsForResource()
            if(elements === undefined){
                return undefined
            }

            return elements[module.ELEMENT_TYPEAHEAD.val()]
        },
        getRecommendedChoices: () => {
            const choices = module.getMappedElement().redcapChoices
            let choiceLines = []
            for(let code in choices){
                let label = choices[code]
                choiceLines.push(code + ', ' + label)
            }

            return choiceLines.join('\n')
        },
        initRecommendedChoiceLinks: () => {
            $('body').on('click', 'a.fhir-services-recommended-choices-link', function(){
                simpleDialog(`
                    <div>
                        The following choices are recommended and represent all valid values for the currently mapped FHIR element.
                        They will be automatically used as the choices for this field if no choices have been specified.
                        If choices have already been specified, they may need to modified to ensure FHIR compatibility.
                        Some modifications to the following are allowed including removing unused values, changing labels, and/or changing codes as long as the label still case insensitively matches one of the recommended codes.
                        Before modifying or removing any choice codes used by existing records, you may need to export this field for all records, update any changed values manually, and import your updates to prevent data loss:
                    </div>
                    <div class='textarea-wrapper'>
                        <textarea readonly>` + module.getRecommendedChoices() + `</textarea>
                        <button onclick='this.previousElementSibling.select()'>Select All (for easy copying)</button
                    </div>
                `, 'Recommended Choices', 'fhir-services-recommended-choices-dialog')
                
                return false
            })
        },
        capitalizeFirstLetter: string => {
            return string.charAt(0).toUpperCase() + string.slice(1);
        },
        initTypeahead: function(options){
            options = $.extend({
                source: [],
                focus: function(){},
                change: function(){}
            }, options)

            var typeahead = $('<input class="x-form-text x-form-field" type="search">')

            typeahead.focus(function(){
                options.focus(typeahead)

                $(function(){
                    typeahead.data("uiAutocomplete").search(typeahead.val());
                })
            })

            typeahead.change(function(){
                options.change(typeahead)

                var source = typeahead.autocomplete('option', 'source');
                if(typeof source[0] !== 'string'){
                    source = source.map(function(item){
                        return item.label
                    })
                }

                if(source.indexOf(typeahead.val()) === -1){
                    typeahead.val('')
                }

                module.updateActionTag()
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
                    typeahead.change()
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
            return module.initTypeahead({
                source: Object.keys(module.schema),
                change: function(typeahead){
                    var elements = module.getElementsForResource()
                    if(elements){
                        module.initElementAutocomplete(module.ELEMENT_TYPEAHEAD, true)
                        module.showElementTypeahead()
                        elementTypeahead.val('')
                        elementTypeahead.focus()
                    }
                    else{
                        module.hideElementTypeahead()
                    }

                    module.updateAdditionalElementVisibility({})
                }
            })
        },
        addAdditionalElementButton: (type) => {
            let button = $("<button class='btn btn-xs btn-rcgreen btn-rcgreen-light'>Add " + type + "</button>")
            button.click((e) => {
                e.preventDefault()
                module.addAdditionalElement(type, '', '')
            })
            
            module.ADDITIONAL_ELEMENT_CONTAINER.find('#fhir-services-additional-element-buttons').append(button)
        },
        initFieldOrValueInput: (type, fieldOrValue) => {
            let fieldOrValueInput
            if(type === module.FIELD){
                fieldOrValueInput = module.initTypeahead({})

                var options = []
                module.fields.forEach((fieldName) => {
                    options.push({
                        label: fieldName,
                        value: fieldName,
                    })
                })

                fieldOrValueInput.autocomplete('option', 'source', options)
            }
            else{
                fieldOrValueInput = $('<input class="x-form-text x-form-field ui-autocomplete-input" type="search" autocomplete="off">')
                fieldOrValueInput.change(() => {
                    module.updateActionTag()
                })
            }

            fieldOrValueInput.val(fieldOrValue)

            return fieldOrValueInput
        },
        addAdditionalElement: (type, elementPath, fieldOrValue) => {
            let elementTypeAhead = module.initTypeahead({})
            module.initElementAutocomplete(elementTypeAhead, false)
            elementTypeAhead.val(elementPath)

            let wrapper = module.createTable({
                'Element': elementTypeAhead,
                [type]: module.initFieldOrValueInput(type, fieldOrValue)
            })

            let removeButton = $(`
                <a href="#" class='fhir-services-remove-additional-element'>
                    <img src="` + module.APP_PATH_IMAGES + `/cross.png">
                </a>
            `)

            removeButton.click(function(e){
                e.preventDefault()
                wrapper.remove()
                module.updateActionTag()
            })
            
            wrapper.prepend(removeButton)

            module.ADDITIONAL_ELEMENT_CONTAINER.find('#fhir-services-additional-elements').append(wrapper)
        },
        updateAdditionalElementVisibility: (mapping) => {
            if(mapping !== undefined){
                module.ADDITIONAL_ELEMENT_CONTAINER.find('#fhir-services-additional-elements').children().remove()
    
                const additionalElements = mapping.additionalElements || {}
                
                for(const [path, details] of Object.entries(additionalElements)){
                    let value = details.field
                    if(value){
                        type = module.FIELD
                    }
                    else{
                        type = module.VALUE
                        value = details.value
                    }
    
                    module.addAdditionalElement(type, path, value)
                }
            }

            if(module.isObservation() && module.ELEMENT_TYPEAHEAD.val() !== ''){
                module.ADDITIONAL_ELEMENT_CONTAINER.show()
            }
            else{
                module.ADDITIONAL_ELEMENT_CONTAINER.hide()
            }
        },
        updateActionTag: () => {
            module.updateRecommendedChoicesVisibility()

            var textarea = module.getActionTagTextArea()
            var tags = textarea.val()

            var details = module.getExistingActionTagDetails()
            if(!details){
                // A error must have occurred.
                return
            }

            var tagStartIndex = details.tagStartIndex
            var tagEndIndex = details.tagEndIndex

            var newTag = module.getActionTagValueFromField()

            if(tagStartIndex > 0 && tags[tagStartIndex-1] !== "\n"){
                newTag = "\n" + newTag
            }

            textarea.val(tags.substring(0, tagStartIndex) + newTag + tags.substring(tagEndIndex))
        },
        getActionTagValueFromField: () => {
            const resource = module.getResourceName()
            const element = module.ELEMENT_TYPEAHEAD.val()

            let newTag = ''
            if(resource != '' && element != ''){
                const additionalElements = module.getAdditionalElementMappings()

                let content
                if($.isEmptyObject(additionalElements)){
                    content = resource + '/' + element
                }
                else{
                    content = module.actionTagEncode({
                        type: resource,
                        primaryElementPath: element,
                        additionalElements: additionalElements
                    })
                }

                newTag = module.ACTION_TAG_PREFIX + content + module.ACTION_TAG_SUFFIX
            }

            return newTag
        },
        getAdditionalElementMappings: () => {
            const additionalElements = {}
            module.ADDITIONAL_ELEMENT_CONTAINER.find('.fhir-services-table-wrapper').each((index, wrapper) => {
                wrapper = $(wrapper)
                const inputs = wrapper.find('input')
                const elementPath = $(inputs[0]).val()
                const fieldOrValueElement = $(inputs[1])
                const type = fieldOrValueElement.closest('tr').find('label').html()
                const fieldOrValue = fieldOrValueElement.val()

                if(elementPath === '' || fieldOrValue === ''){
                    return
                }

                if(additionalElements[elementPath] === undefined){
                    additionalElements[elementPath] = {}
                }
                
                additionalElements[elementPath][type] = fieldOrValue
            })

            return additionalElements
        },
        updateRecommendedChoicesVisibility: () => {
            const element = module.getMappedElement()
            if(element && element.redcapChoices){
                module.RECOMMENDED_CHOICES_LINK.show()
            }
            else{
                module.RECOMMENDED_CHOICES_LINK.hide()
            }
        },
        initElementAutocomplete: function(typeahead, primary){
            var elements = module.getElementsForResource()

            var options = []
            for(var path in elements){
                // The "^" is an XOR operator.
                if(primary ^ module.isPrimaryElement(path)){
                    continue
                }

                options.push({
                    label: path,
                    value: path,
                    description: elements[path].description
                })
            }

            typeahead.autocomplete('option', 'source', options)
        },
        isObservation: () => {
            return module.getResourceName() === 'Observation'
        },
        isPrimaryElement: (path) => {
            if(module.isObservation()){
                return [
                    'valueQuantity/value',
                    'valueCodeableConcept',
                    'valueString',
                    'valueBoolean',
                    'valueInteger',
                    'valueRange/low/value',
                    'valueRange/high/value',
                    'valueRatio/numerator/value',
                    'valueRatio/denominator/value',
                    'valueTime',
                    'valueDateTime',
                    'valuePeriod/start',
                    'valuePeriod/end'
                ].indexOf(path) !== -1
            }

            return true
        },
        getResourceName: () => {
            return module.RESOURCE_TYPEAHEAD.val()
        },
        getElementsForResource: function(){
            return module.schema[module.getResourceName()]
        },
        getActionTagTextArea: function(){
            return $('#div_field_annotation textarea')
        },
        getExistingActionTagDetails(){
            var textarea = module.getActionTagTextArea()
            var tags = textarea.val()

            var tagPrefix = module.ACTION_TAG_PREFIX
            var tagSuffix = module.ACTION_TAG_SUFFIX

            var tagStartIndex = tags.indexOf(tagPrefix)
            var tagEndIndex
            var value
            if(tagStartIndex === -1){
                tagStartIndex = tags.length
                tagEndIndex = tags.length
                value = ''
            }
            else{
                tagEndIndex = tags.indexOf(tagSuffix, tagStartIndex+tagPrefix.length)
                if(tagEndIndex === -1){
                    alert("Corrupt action tag detected.  Please remove what's left of the " + tagPrefix + tagSuffix + " action tag.")
                    return false
                }
                else{
                    tagEndIndex++ // put it past the end of the tag
                }

                value = tags.substring(tagStartIndex + tagPrefix.length, tagEndIndex-tagSuffix.length)
            }

            return {
                tagStartIndex: tagStartIndex,
                tagEndIndex: tagEndIndex,
                value: value
            }
        }
    })

    module.init()
})