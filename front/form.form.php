<?php
include ("../../../inc/includes.php");

// Check if plugin is activated...
$plugin = new Plugin();
if ($plugin->isActivated("formcreator")) {
   $form = new PluginFormcreatorForm();

   // Add a new Form
   if (isset($_POST["add"])) {
      Session::checkRight("entity", UPDATE);
      $newID = $form->add($_POST);

      Html::redirect($CFG_GLPI["root_doc"] . '/plugins/formcreator/front/form.form.php?id=' . $newID);

      // Edit an existing form
   } else if (isset($_POST["update"])) {
      Session::checkRight("entity", UPDATE);
      $form->update($_POST);
      Html::back();

      // Delete a form (is_deleted = true)
   } else if (isset($_POST["delete"])) {
      Session::checkRight("entity", UPDATE);
      $form->delete($_POST);
      $form->redirectToList();

      // Restore a deleteted form (is_deleted = false)
   } else if (isset($_POST["restore"])) {
      Session::checkRight("entity", UPDATE);
      $form->restore($_POST);
      $form->redirectToList();

      // Delete defenitively a form from DB and all its datas
   } else if (isset($_POST["purge"])) {
      Session::checkRight("entity", UPDATE);
      $form->delete($_POST, 1);
      $form->redirectToList();

      // Import form
   } else if (isset($_GET["import_form"])) {
      Session::checkRight("entity", UPDATE);
      Html::header(
         PluginFormcreatorForm::getTypeName(2),
         $_SERVER['PHP_SELF'],
         'admin',
         'PluginFormcreatorForm',
         'option'
      );

      if (version_compare(GLPI_VERSION, '9.2', 'ge')) {
         Html::requireJs('fileupload');
      }

      $form->showImportForm();
      Html::footer();

      // Import form
   } else if (isset($_GET["import_send"])) {
      Session::checkRight("entity", UPDATE);
      $form->importJson($_REQUEST);
      Html::back();

      // Save form to target
   } else if (isset($_POST['submit_formcreator'])) {
      if ($form->getFromDB($_POST['formcreator_form'])) {

         // If user is not authenticated, create temporary user
         if (!isset($_SESSION['glpiname'])) {
            $_SESSION['glpiname'] = 'formcreator_temp_user';
         }

         // Save form
         if (!$form->saveForm()) {
            Html::back();
         }
         $form->increaseUsageCount();

         // If user was not authenticated, remove temporary user
         if ($_SESSION['glpiname'] == 'formcreator_temp_user') {
            unset($_SESSION['glpiname']);
            Html::back();
         } else if (plugin_formcreator_replaceHelpdesk()) {
            Html::redirect('issue.php');
         } else {
            Html::redirect('formlist.php');
         }
      }


      // Show forms form
   } else {
      Session::checkRight("entity", UPDATE);

      Html::header(
         PluginFormcreatorForm::getTypeName(2),
         $_SERVER['PHP_SELF'],
         'admin',
         'PluginFormcreatorForm',
         'option'
      );

      if (version_compare(GLPI_VERSION, '9.2', 'ge')) {
         Html::requireJs('tinymce');
      }

      $_GET['id'] = isset($_GET['id']) ? intval($_GET['id']) : -1;
      $form->display($_GET);

      Html::footer();
   }

   // Or display a "Not found" error
} else {
   Html::displayNotFoundError();
}
