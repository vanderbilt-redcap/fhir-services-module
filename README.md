# The FHIR Services module

This module is a work in progress prototyping the following FHIR related features (with more to follow in the future):

* Downloading any REDCap instrument as a FHIR Questionnaire
  * This feature can be used from the **Online Designer** by clicking **Choose action** next to any instrument and selecting **Download instrument as FHIR Questionnaire**.
* Selecting a **Project Type** of **Questionnaire** will make the following features available
  * The following features are found under the **Questionnaire Options** link in the left menu:
    * Behaving as a FHIR server and receiving/viewing FHIR resources via the **View Received Resources** button
        * This actually works for any received **Resource** (not just **Questionnaires**).
    * Uploading a **Questionnaire** to replace the **Data Dictionary** via the **Data Dictionary Options** button
    * Importing a **QuestionnaireResponse** as a record via the **Import a FHIR QuestionnaireResponse**
  * The following features are found on the **Record Home Page** under **Choose action for record**, and only work if the Data Dictionary was generated using a **Questionnaire**.
    * **Open FHIR QuestionnaireResponse**
    * **Send FHIR QuestionnaireResponse to remote FHIR server**
      * The **Remote FHIR Server URL** can be specified via the standard module configuration dialog.
* Selecting a **Project Type** of **Composition** should be ignored by most users for now.  It enables experimental features for generating project specific **Composition** and **Bundle** resources that require a specific Data Dictionary.

A big **THANK YOU** to Daniel Carbone for creating the [php-fhir](https://github.com/dcarbone/php-fhir) library that makes this module possible!