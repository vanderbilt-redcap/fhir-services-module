# The FHIR Services module

This module is a work in progress prototyping the following FHIR related features (with more to follow in the future):

* Mapping REDCap fields to FHIR Resources & Elements
  * To map a field, complete the **FHIR Mapping** section when editing that field in the Online Designer.
  * For Resources that required fields to be associated with each other (like Observations), use the **Additional Elements** settings at the bottom of the **FHIR Mapping** section.
  * Not all Resources or complex data structures can be mapped currently, but this module can always be expanded to support more scenarios as needed.
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