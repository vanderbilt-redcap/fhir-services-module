# The FHIR Services module

This module is a work in progress prototyping the following FHIR related features (with more to follow in the future):

* Mapping REDCap fields to FHIR Resources & Elements (limited, but expanding)
  * To map a field, complete the **FHIR Mapping** section when editing that field in the Online Designer.
  * Top level Resources that can have multiple instances for a single record require fields to be associated with each other using the **Additional Elements** settings at the bottom of the **FHIR Mapping** section.  Common examples of such Resources include Observation, Condition, and Immunization.
  * Not all Resources or REDCap data structures can be mapped currently, but this module can be expanded to support more scenarios as needed.  Support for some new scenarios can be added in a few hours of development time, while other may take hundreds of hours depending on the specifics.  Here are some technical details on what is and isn't currently supported:
    * REDCap fields (that are not in events or repeating instances) can currently be mapped to a single instance of any FHIR Resource & element, including a single instance of any child Resources & elements contained within the selected top level Resource (as opposed to referenced by it).  We have partial support for mapping REDCap events & repeating instances, as well as repeating FHIR Resources & elements.  There are too many combinations of edge cases on either side to list, but we are attempting to implement generalized solutions in the order if which use cases are most common.
    * It is currently assumed that only a single instance of the Patient Resource will ever be mapped per REDCap record. This Patient will be automatically linked to other Resources in Bundles via the subject/patient reference elements on each Resource.
    * Observation/code is currently limited to LOINC codes
    * REDCap to FHIR mapping involves an almost infinite number of edge cases... Whether or not this feature will work for you in its current state is highly dependant on the details of your particular project's design.  However, all evidence so far points to any imaginable mapping being feasible.  We anticipate that at some point we will reach a critical mass of test cases and generalized solutions that will cover the large majority of data that can be represented in both REDCap & FHIR.  We are currently looking for partners to help justify, implement, and verify further work.
* Behaving as a FHIR server and sending/receiving FHIR resources
  * The FHIR resource for the current record can be viewed or sent by going to the **Record Home Page**, clicking **Choose action for record**, and selecting the **View FHIR...**, **Validate FHIR...**, or **Send FHIR...** options.
  * The **Send FHIR...** option will send the current record to the **Remote FHIR Server URL** specified in the module configuration.
  * Any FHIR resources can be received under the current project at the "base URL" found at the top of the **Received FHIR Resources** page in the left menu.
  * For easy testing, this module can also send FHIR resources to itself on the current project or another project on the same system.  This can be accomplished by entering the "base URL" at the top of the **Received FHIR Resources** page into the "Remote FHIR Server URL" setting.
* Sending/Viewing FHIR Consent resources that represent eConsent survey submissions
  * If eConsent is configured on a survey, FHIR Consent resources can be automatically sent to the **Remote FHIR Server URL** by selecting the "Automatically send completed eConsents..." configuration checkbox, and completing the "eConsent Form Settings" below it for each eConsent form.
  * For testing, FHIR Consent resources can be viewed by opening the completed eConsent form and clicking the "View eConsent FHIR Bundle" button at the top.
* Downloading any REDCap instrument as a FHIR Questionnaire
  * This feature can be used from the **Online Designer** by clicking **Choose action** next to any instrument and selecting **Download instrument as FHIR Questionnaire**.
* Selecting a **Project Type** of **Questionnaire** will make the following features available
  * The following features are found under the **Questionnaire Options** link in the left menu:
    * Uploading a **Questionnaire** to replace the **Data Dictionary** via the **Data Dictionary Options** button
    * Importing a **QuestionnaireResponse** as a record via the **Import a FHIR QuestionnaireResponse**
* Selecting a **Project Type** of **Composition** should be ignored by most users for now.  It enables experimental features for generating project specific **Composition** and **Bundle** resources that require a specific Data Dictionary.