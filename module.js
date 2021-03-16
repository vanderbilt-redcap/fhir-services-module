$(function(){
    var module = $.extend(FHIRServicesExternalModule, {
        RECOMMENDED_CHOICES_LINK: $("<a href='#' class='fhir-services-recommended-choices-link'>View the recommended choices for this element</a>"),
        RECOMMENDED_CHOICES_DIALOG_ID: 'fhir-services-invalid-choices-dialog',
        init: function(){
            var elementTypeahead = module.initTypeahead({})
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

            module.addAdditionalElementButton('Field')
            module.addAdditionalElementButton('Value')

            typeaheadContainer.append(module.ADDITIONAL_ELEMENT_CONTAINER)
            
            var openAddQuesFormVisible = window.openAddQuesFormVisible
            window.openAddQuesFormVisible = function(){
                openAddQuesFormVisible.apply(null, arguments);

                var details = module.getExistingActionTagDetails()
                if(!details){
                    // A error must have occurred.
                    return
                }
                
                if(details.value === ''){
                    /**
                     * The previously selected resource is intentionally left in place to make it
                     * easy to map several fields for the same resource in a row.
                     */
                    elementTypeahead.val('')
                    module.hideElementTypeahead()
                }
                else{
                    var parts = details.value.split('/')
                    resourceTypeahead.val(parts.shift())
                    elementTypeahead.val(parts.join('/'))

                    module.initElementAutocomplete()
                }
                
                module.updateRecommendedChoicesVisibility()
                module.updateAdditionalFieldVisibility()

                if(resourceTypeahead.val() !== ''){
                    module.showElementTypeahead()
                }
            }

            $('#div_field_req').before(typeaheadContainer)

            module.initSaveButton()
            module.initRecommendedChoiceLinks()
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

            let wrapper = $('<div class="fhir-services-additional-element-wrapper" />')
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
                blur: function(){}
            }, options)

            var typeahead = $('<input class="x-form-text x-form-field" type="search">')

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
            return module.initTypeahead({
                source: Object.keys(module.schema),
                blur: function(typeahead){
                    var elements = module.getElementsForResource()
                    if(elements){
                        module.initElementAutocomplete()
                        elementTypeahead.val('')
                        elementTypeahead.focus()
                    }
                    else{
                        module.hideElementTypeahead()
                    }

                    module.updateAdditionalFieldVisibility()
                }
            })
        },
        updateAdditionalFieldVisibility: () => {
            if(module.isObservation()){
                module.showAdditionalElements()
            }
            else{
                module.hideAdditionalElements()
            }
        },
        addAdditionalElementButton: (type) => {
            let button = $("<button class='btn btn-xs btn-rcgreen btn-rcgreen-light'>Add " + type + "</button>")
            button.click((e) => {
                e.preventDefault()

                let wrapper = module.createTable({
                    'Element': $('<input class="x-form-text x-form-field ui-autocomplete-input" type="search" autocomplete="off">'),
                    [type]: $('<input class="x-form-text x-form-field ui-autocomplete-input" type="search" autocomplete="off">')
                })

                let removeButton = $(`
                    <a href="#" class='fhir-services-remove-additional-element'>
                        <img src="` + module.APP_PATH_IMAGES + `/cross.png">
                    </a>
                `)

                removeButton.click(function(e){
                    e.preventDefault()
                    wrapper.remove()
                })
                
                wrapper.prepend(removeButton)
    
                module.ADDITIONAL_ELEMENT_CONTAINER.find('#fhir-services-additional-elements').append(wrapper)
            })
            
            module.ADDITIONAL_ELEMENT_CONTAINER.find('#fhir-services-additional-element-buttons').append(button)
        },
        showAdditionalElements: () => {
           module.ADDITIONAL_ELEMENT_CONTAINER.show()
        },
        hideAdditionalElements: () => {
            module.ADDITIONAL_ELEMENT_CONTAINER.hide()
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

            var resource = module.getResourceName()
            var element = module.ELEMENT_TYPEAHEAD.val()

            var newTag = ''
            if(resource != '' && element != ''){
                newTag = module.ACTION_TAG_PREFIX + resource + '/' + element + module.ACTION_TAG_SUFFIX
            }

            if(tagStartIndex > 0 && tags[tagStartIndex-1] !== "\n"){
                newTag = "\n" + newTag
            }

            textarea.val(tags.substring(0, tagStartIndex) + newTag + tags.substring(tagEndIndex))
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
        initElementAutocomplete: function(){
            var elements = module.getElementsForResource()

            var options = []
            for(var path in elements){
                if(!module.isElementVisible(path)){
                    continue
                }

                options.push({
                    label: path,
                    value: path,
                    description: elements[path].description
                })
            }

            module.ELEMENT_TYPEAHEAD.autocomplete('option', 'source', options)
            module.showElementTypeahead()
        },
        isObservation: () => {
            return module.getResourceName() === 'Observation'
        },
        isElementVisible: (path) => {
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