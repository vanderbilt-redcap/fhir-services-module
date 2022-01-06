$(function(){
    var module = $.extend(FHIRServicesExternalModule, {
        RECOMMENDED_CHOICES_LINK: $("<a href='#' class='fhir-services-recommended-choices-link'>View the recommended choices for this element</a>"),
        RECOMMENDED_CHOICES_DIALOG_ID: 'fhir-services-invalid-choices-dialog',
        FIELD: 'field',
        VALUE: 'value',
        init: function(){
            var elementTypeahead = module.initTypeahead({})
            var resourceTypeahead = module.initResourceTypeahead(elementTypeahead)
            var systemTypeahead = module.initTypeahead({})

            module.RESOURCE_TYPEAHEAD = resourceTypeahead
            module.ELEMENT_TYPEAHEAD = elementTypeahead
            module.SYSTEM_TYPEAHEAD = systemTypeahead

            var typeaheadContainer = $('<div id="fhir-services-mapping-field-settings" style="border: 1px solid rgb(211, 211, 211); padding: 4px 8px; margin-top: 5px; display: block;"><b>FHIR Mapping</b></div>')
            typeaheadContainer.append(module.createTable({
                'Resource': resourceTypeahead,
                'Element': elementTypeahead,
                'System': systemTypeahead
            }))

            typeaheadContainer.append(module.RECOMMENDED_CHOICES_LINK)

            module.ADDITIONAL_ELEMENT_CONTAINER = $(`
                <div>
                    <b class='fhir-services-additional-element-header'>
                        Additional Elements
                        <a href='javascript:;' class='help' onclick="simpleDialog(
                            \`
                                <p>
                                    <b>Additional Elements</b> can be used to associate values and/or fields other than the current one with the same <b>Resource</b> instance as the field currently being edited.  This will not necessarily be the top level <b>Resource</b> mapped, depending on whether the <b>Element</b> path references a nested/child <b>Resource</b>.
                                </p>
                                <p>
                                    <b>Additional Elements</b> can also be used to simply include values in the FHIR export that are not stored in a field and are the same for every record.
                                </p>
                                <p>
                                    For <b>Elements</b> that will not exist more than once per record in the FHIR export, it likely makes more sense to edit & map each individually instead of using <b>Additional Elements</b>.
                                </p>
                            \`,
                            'Additional Elements'
                        )">?</a>
                    </b>
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

                    if(mapping.type !== 'Questionnaire'){
                        elementTypeahead.val(mapping.primaryElementPath)
                        systemTypeahead.val(mapping.primaryElementSystem)
                        module.toggleTypeaheadRow(systemTypeahead, mapping.primaryElementSystem !== undefined)

                        module.initElementAutocomplete(elementTypeahead, true)
                        module.showElementTypeahead()
                    }
                }
                
                module.updateRecommendedChoicesVisibility()
                module.setAdditionalElementsFromMapping(mapping)

                if(resourceTypeahead.val() !== ''){
                    if(resourceTypeahead.val() !== 'Questionnaire'){
                        module.showElementTypeahead()
                    }
                }
            }

            $('#div_field_req').before(typeaheadContainer)

            // Does this spot correctly account for previously set values on page load?
            module.setupSystemDropdown(systemTypeahead, elementTypeahead, null)

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

            if(Array.isArray(rows)){
                rows.forEach((row) => {
                    table.append(row)
                })
            }
            else{
                for(let label in rows){
                    table.append(module.createRow(label, rows[label]))
                }
            }

            let wrapper = $('<div class="fhir-services-table-wrapper" />')
            wrapper.append(table)

            return wrapper
        },
        createRow: (label, field) => {
            let row = $('<tr></tr>')

            let addColumn = function(content){
                let column = $('<td></td>')
                column.append(content)
                row.append(column)
            }

            addColumn('<label>' + label + '</label>')
            addColumn(field)

            return row
        },
        initSaveButton: function(){
            var addEditFieldSave = window.addEditFieldSave
            var finishSave = function(){
                addEditFieldSave.apply(null, arguments);
            }

            window.addEditFieldSave = function(){
                const choices = module.getREDCapChoices()
                if($.isEmptyObject(choices)){
                    // An element with choices is not mapped
                    finishSave()
                    return
                }

                var validValues = {}
                for(const [code, value] of Object.entries(choices)){
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
        getMappedElement: function(elementPath = undefined){
            const elements = module.getElementsForResource()
            if(elements === undefined){
                return undefined
            }
            
            if(elementPath === undefined){
                elementPath = module.ELEMENT_TYPEAHEAD.val()
            }

            return elements[elementPath]
        },
        getREDCapChoices: (elementPath = undefined) => {
            const elementDetails = module.getMappedElement(elementPath)
            return elementDetails?.redcapChoices || {}
        },
        getRecommendedChoices: () => {
            const choices = module.getREDCapChoices()
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
                var source = typeahead.autocomplete('option', 'source');
                if(source.length > 0){
                    if(typeof source[0] !== 'string'){
                        source = source.map(function(item){
                            return item.label
                        })
                    }
    
                    if(source.indexOf(typeahead.val()) === -1){
                        typeahead.val('')
                    }
                }

                options.change(typeahead)
                
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
                    typeahead.val(result.item.label)
                    typeahead.change()

                    // Avoid the default action of putting the value of the selected option in the <input>.
                    return false
                }
            })
            .autocomplete( "instance" )._renderItem = function( ul, item ){
                var label = item.label

                if(item.description){
                    label = "<b style='font-weight: 600;' title='" + module.htmlEscape(item.description) + "'>" + item.label + "</b>"
                }

                return $( "<li />" )
                    .append('<div>' + label + '</div>')
                    .appendTo( ul );
            }

            return typeahead
        },
        htmlEscape: function(s){
            return $("<div>").text(s).html()
        },
        initResourceTypeahead: function(elementTypeahead){
            return module.initTypeahead({
                source: Object.keys(module.schema),
                change: function(typeahead){
                    var elements = module.getElementsForResource()
                    if(elements && module.getResourceName() !== 'Questionnaire'){
                        module.initElementAutocomplete(module.ELEMENT_TYPEAHEAD, true)
                        module.showElementTypeahead()
                        elementTypeahead.val('')
                        elementTypeahead.focus()
                    }
                    else{
                        module.hideElementTypeahead()
                    }

                    module.addDefaultAdditionalElements()
                }
            })
        },
        addAdditionalElementButton: (type) => {
            let button = $("<button class='btn btn-xs btn-rcgreen btn-rcgreen-light'>Add " + type + "</button>")
            button.click((e) => {
                e.preventDefault()
                module.addAdditionalElement(type, '', '', '')
            })
            
            module.ADDITIONAL_ELEMENT_CONTAINER.find('#fhir-services-additional-element-buttons').append(button)
        },
        initFieldOrValueInput: (type, fieldOrValue, elementTypeAhead, systemTypeAhead) => {
            const fieldOrValueInput = module.initTypeahead({})
            module.setupSystemDropdown(systemTypeAhead, elementTypeAhead, fieldOrValueInput)

            if(type === module.FIELD){
                const options = []
                module.fields.forEach((fieldName) => {
                    options.push({
                        value: fieldName,
                        label: fieldName,
                    })
                })

                fieldOrValueInput.autocomplete('option', 'source', options)
            }
            else{
                const setOptions = () => {
                    return module.setValueDropdownOptions(fieldOrValueInput, systemTypeAhead.val(), elementTypeAhead.val(), fieldOrValue)
                }

                fieldOrValue = setOptions()
                elementTypeAhead.change(setOptions)
                systemTypeAhead.change(setOptions)
            }

            fieldOrValueInput.val(fieldOrValue)

            return fieldOrValueInput
        },
        toggleTypeaheadRow: (typeahead, flag) => {
            typeahead.closest('tr').toggle(flag)
        },
        setupSystemDropdown: (systemTypeAhead, elementTypeAhead, fieldOrValueInput) => {
            const action = () => {
                const elementPath = elementTypeAhead.val()
                const elementDetails = module.getMappedElement(elementPath) || {}

                const isCodingCode = elementDetails.parentResourceName === 'Coding' && elementPath.endsWith('/code')
                if(isCodingCode){
                    if(systemTypeAhead.val() === ''){
                        // Enter the first system for this element
                        const system = elementDetails.system
                        if(system){
                            systemTypeAhead.val(system)
                        }
                    }
                }
                else{
                    systemTypeAhead.val('')
                }

                module.toggleTypeaheadRow(systemTypeAhead, isCodingCode)
                module.updateActionTag()
            }

            action()
            elementTypeAhead.change(()=>{
                action()

                if(fieldOrValueInput !== null){
                    fieldOrValueInput.val('') // Force the user to re-enter field/value, since the previous one is likely no longer valid.
                    fieldOrValueInput.focus()
                }
            })
        },
        setValueDropdownOptions: (fieldOrValueInput, system, elementPath, selectedValue) => {
            const options = []
            let returnValue = selectedValue // return the raw value if no options exist

            const elementDetails = module.getMappedElement(elementPath) || {}

            let choices = [];
            if(system === '' || system === elementDetails.system){
                choices = module.getREDCapChoices(elementPath)
            }
            
            for(const [value, label] of Object.entries(choices)){
                options.push({
                    value: value,
                    label: label,
                })

                if(value === selectedValue){
                    // Show the label instead of the value
                    returnValue = label
                }
            }

            fieldOrValueInput.autocomplete('option', 'source', options)

            console.log('setValueDropdownOptions', options)

            return returnValue
        },
        addAdditionalElement: (type, elementPath, system, fieldOrValue) => {
            let elementTypeAhead = module.initTypeahead({})
            module.initElementAutocomplete(elementTypeAhead, false)
            elementTypeAhead.val(elementPath)

            const systemTypeAhead = module.initTypeahead({})
            systemTypeAhead.val(system)
            
            let wrapper = module.createTable([
                module.createRow('Element', elementTypeAhead),
                module.createRow('System', systemTypeAhead)
                    .addClass('system')
                    .hide(),
                module.createRow(type, module.initFieldOrValueInput(type, fieldOrValue, elementTypeAhead, systemTypeAhead))
            ])

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

            if(elementPath === ''){
                // The user just clicked one of the "Add" buttons.  Focus the element.
                elementTypeAhead.focus()
            }
        },
        setAdditionalElementsFromMapping: (mapping) => {
            module.removeAdditionalElements()
    
            const additionalElements = mapping.additionalElements || []
        
            let system = ''
            additionalElements.forEach((details, i) => {
                const nextDetails = additionalElements[i+1]
                if(nextDetails !== undefined){
                    if(
                        details.element.endsWith('/system')
                        &&
                        // Since system is not included in the schema, we check to see if the next element is Coding/code.
                        module.getMappedElement(nextDetails.element).parentResourceName === 'Coding'
                    ){
                        // Store the system for use with the next loop iteration and continue
                        // Since we always store coding/system directly above coding/code
                        system = details.value
                        return
                    }
                }

                let value = details.field
                if(value){
                    type = module.FIELD
                }
                else{
                    type = module.VALUE
                    value = details.value
                }

                module.addAdditionalElement(type, details.element, system, value)
                system = '' // Prevent the system from being used for later loop iterations
            })
        },
        addDefaultAdditionalElements: () => {
            if(module.getResourceName() === 'Observation' && module.getAdditionalElementMappings().length === 0){
                module.addAdditionalElement(module.VALUE, 'status', '', 'final')
                module.addAdditionalElement(module.VALUE, 'code/coding/code', '', '')
            }
        },
        removeAdditionalElements: () => {
            module.ADDITIONAL_ELEMENT_CONTAINER.find('#fhir-services-additional-elements').children().remove()
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
            if(resource != ''){
                if(element != '' || resource === 'Questionnaire'){
                    let primaryElementSystem = module.SYSTEM_TYPEAHEAD.val()
                    if(primaryElementSystem === ''){
                        primaryElementSystem = undefined
                    }

                    const additionalElements = module.getAdditionalElementMappings()
    
                    let content
                    if(primaryElementSystem === undefined && $.isEmptyObject(additionalElements)){
                        content = resource
                        if(element != ''){
                            content += '/' + element
                        }
                    }
                    else{
                        content = module.actionTagEncode({
                            type: resource,
                            primaryElementPath: element,
                            primaryElementSystem: primaryElementSystem,
                            additionalElements: additionalElements
                        })
                    }
    
                    newTag = module.ACTION_TAG_PREFIX + content + module.ACTION_TAG_SUFFIX
                }
            }

            return newTag
        },
        getAdditionalElementMappings: () => {
            const additionalElements = []
            module.ADDITIONAL_ELEMENT_CONTAINER.find('.fhir-services-table-wrapper').each((index, wrapper) => {
                wrapper = $(wrapper)
                const inputs = wrapper.find('input')
                const elementPath = $(inputs[0]).val()
                const system = $(inputs[1]).val()
                const fieldOrValueElement = $(inputs[2])
                const type = fieldOrValueElement.closest('tr').find('label').html()
                const fieldOrValue = fieldOrValueElement.val()

                if(elementPath === '' || fieldOrValue === ''){
                    return
                }

                if(system !== ''){
                    // Insert the system mapping directly above the code mapping
                    additionalElements.push({
                        element: elementPath.replaceAll('/code', '/system'),
                        value: system
                    })    
                }

                additionalElements.push({
                    element: elementPath,
                    [type]: module.getValueForLabel(fieldOrValueElement, fieldOrValue)
                })
            })

            return additionalElements
        },
        getValueForLabel: (fieldOrValueElement, fieldOrValue) => {
            const options = fieldOrValueElement.autocomplete('option', 'source')
            if(options.length === 0){
                return fieldOrValue
            }

            for(let i in options){
                let option = options[i]
                if(option.label === fieldOrValue){
                    return option.value
                }
            }

            alert("Field/Value not found for dropdown:", fieldOrValue)
        },
        updateRecommendedChoicesVisibility: () => {
            if($.isEmptyObject(module.getREDCapChoices())){
                module.RECOMMENDED_CHOICES_LINK.hide()
            }
            else{
                module.RECOMMENDED_CHOICES_LINK.show()
            }
        },
        initElementAutocomplete: function(typeahead, primary){
            var elements = module.getElementsForResource()

            var options = []
            for(var path in elements){
                options.push({
                    label: path,
                    value: path,
                    description: elements[path].description
                })
            }

            typeahead.autocomplete('option', 'source', options)
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