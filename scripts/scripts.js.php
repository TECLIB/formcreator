<?php
include ('../../../inc/includes.php');
header('Content-Type: text/javascript');
?>

var modalWindow;
var rootDoc          = "<?php echo $CFG_GLPI['root_doc']; ?>";
var currentCategory  = "0";
var sortByName = false;
var tiles = [];

// === MENU ===
var link = '';
link += '<li id="menu7">';
link += '<a href="' + rootDoc + '/plugins/formcreator/front/formlist.php" class="itemP">';
link += "<?php echo _n('Form','Forms', 2, 'formcreator'); ?>";
link += '</a>';
link += '</li>';

jQuery(document).ready(function($) {
   var target = $('body');
   modalWindow = $("<div></div>").dialog({
      width: 980,
      autoOpen: false,
      height: "auto",
      modal: true,
      position: ['center', 50]
   });

   <?php
      if (isset($_SESSION['glpiactiveprofile']['interface'])
            && ($_SESSION['glpiactiveprofile']['interface'] == 'helpdesk')) {
         echo "$('#c_menu #menu1:first-child').after(link);";
      }
   ?>

   var NomDuFichier = document.location.href.substring(document.location.href.lastIndexOf("/") + 1);

   if (NomDuFichier == "central.php") {
      $('#tabspanel + div.ui-tabs').on("tabsload", function( event, ui ) {
         showFormList()
      });
   } else if (NomDuFichier == "helpdesk.public.php") {
      showFormList();
   } else if ($('#plugin_formcreator_wizard_categories').length > 0) {
      updateCategoriesView();
      updateWizardFormsView(0);

      // Setup events
      $('#plugin_formcreator_wizard_right a.mostPopularSort').click(
         function () {
            sortByName = false;
            showTiles(tiles);
         }
      );

     $('#plugin_formcreator_wizard_right a.alphabeticSort').click(
        function () {
           sortByName = true;
           showTiles(tiles);
         }
      );

   }

   // Initialize search bar
   searchInput = $('#plugin_formcreator_searchBar input:first');
   if (searchInput.length == 1) {
      // Dynamically update forms and faq items while the user types in the search bar
      var timer;
      searchInput.keyup(
         function(event) {
            if (typeof timer != "undefined") {
               clearTimeout(timer);
            }
            if (event.which == 13) {
               updateWizardFormsView(currentCategory);
            } else {
               timer = setTimeout(function() {
                  updateWizardFormsView(currentCategory);
               }, 300);
            }
            if (searchInput.val().length == 0) {
               $('#plugin_formcreator_searchBar').removeClass('clearable');
            } else {
               $('#plugin_formcreator_searchBar').addClass('clearable');
            }
         }
      );
      // Clear the search bar if it gains focus
      $('#plugin_formcreator_searchBar input').focus(function(event) {
         if (searchInput.val().length > 0) {
            searchInput.val('');
            $('#plugin_formcreator_searchBar').removeClass('clearable');
            updateWizardFormsView(currentCategory);
            $.when(getFormAndFaqItems(0)).then(
               function (response) {
                  tiles = response;
                  showTiles(tiles);
               }
            );
         }
      });
   }

   // === Add better multi-select on form configuration validators ===
   // initialize the pqSelect widget.
   $('#tabspanel + div.ui-tabs').on("tabsload", function( event, ui ) {
      $("#validator_users").pqSelect({
          multiplePlaceholder: '----',
          checkbox: true //adds checkbox to options
      });
      $("#validator_groups").pqSelect({
          multiplePlaceholder: '----',
          checkbox: true //adds checkbox to options
      });
   });
});

function showFormList() {
   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/homepage_forms.php',
      type: "GET"
   }).done(function(response){
      $('.central > tbody:first').first().prepend(response);
   });
}

function updateCategoriesView() {
   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/homepage_wizard.php',
      data: {wizard: 'categories'},
      type: "GET",
      dataType: "json"
   }).done(function(response) {
      html = '<div class="slinky-menu">';
      html = html + buildCategoryList(response);
      html = html + '</div>';

      //Display categories
      $('#plugin_formcreator_wizard_categories').empty();
      $('#plugin_formcreator_wizard_categories').prepend(html);

      // Setup slinky
      $('#plugin_formcreator_wizard_categories div:nth(0)').slinky({ title: true, label: '<?php echo __('Back', 'formcreator') ?>'});
      $('#plugin_formcreator_wizard_categories a.back').click(
         function() {
            parentItem = $(event.target).parentsUntil('#plugin_formcreator_wizard_categories', 'li')[1];
            parentAnchor = $(parentItem).children('a')[0];
            updateWizardFormsView(parentAnchor.getAttribute('data-parent-category-id'));
         }
      );

      $('#plugin_formcreator_wizard_categories a[data-category-id]').click(
         function (event) {
            updateWizardFormsView(event.target.getAttribute('data-category-id'));
         }
      );
   });
}

/**
 * get form and faq items from DB
 * Returns a promise
 */
function getFormAndFaqItems(categoryId) {
   currentCategory = categoryId;
   keywords = $('#plugin_formcreator_searchBar input:first').val();
   deferred = jQuery.Deferred();
   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/homepage_wizard.php',
      data: {wizard: 'forms', categoriesId: categoryId, keywords: keywords},
      type: "GET",
      dataType: "json"
   }).done(function (response) {
      deferred.resolve(response);
   }).fail(function () {
      deferred.reject();
   });
   return deferred;
}

function sortFormAndFaqItems(items, byName) {
   if (byName == true) {
      // sort by name
      items.sort(function (a, b) {
         if (a.name < b.name) {
            return -1;
         }
         if (a.name > b.name) {
            return 1
         }
         return 0;
      });
   } else {
      // sort by view or usage count
      items.sort(function (a, b) {
         if (a.usage_count > b.usage_count) {
            return -1;
         }
         if (a.usage_count < b.usage_count) {
            return 1
         }
         return 0;
      });
   }
   return items;
}

function showTiles(tiles) {
   tiles = sortFormAndFaqItems(tiles, sortByName);
   html = buildTiles(tiles);

   //Display tiles
   $('#plugin_formcreator_wizard_forms').empty();
   $('#plugin_formcreator_wizard_forms').prepend(html);
   $('#plugin_formcreator_formlist').masonry();
}

function showMostPopular() {
   $.ajax({
      url: rootDoc + '/plugins/formcreator/ajax/homepage_wizard.php',
      data: {wizard: 'mostPopular'},
      type: "GET",
      dataType: "json"
   }).done(function(response){
      html = buildTiles(response);

      // Display all tiles
      $('#plugin_formcreator_wizard_forms').empty();
      $('#plugin_formcreator_wizard_forms').prepend(html);
      $('#plugin_formcreator_formlist').masonry();
   });
}

function updateWizardFormsView(categoryId) {
   $.when(getFormAndFaqItems(categoryId)).then(
      function (response) {
         tiles = response;
         showTiles(tiles);
      }
   );
}

function buildCategoryList(tree) {
   if (tree.id != 0) {
      html = '<a href="#" data-parent-category-id="' + tree.parent +'"'
         + ' data-category-id="' + tree.id + '"'
         + ' onclick="updateWizardFormsView(' + tree.id + ')">'
         + tree.name
         + '</a>';
   } else {
      html = '';
   }
   if (Object.keys(tree.subcategories).length == 0) {
      return html;
   }
   html = html + '<ul>';
   $.each(tree.subcategories, function (key, val) {
      html = html + '<li>' + buildCategoryList(val) + '</li>';
   });
   html = html + '</ul>';
   return html;
}

function buildTiles(list) {
   $(document).on('click', '.plugin_formcreator_formTile', function(){
      document.location = $(this).children('a').attr('href');
   });

   if (list.length == 0) {
      html = '<div id="plugin_formcreator_formlist">'
      + "<?php echo __('No form yet in this category', 'formcreator') ?>"
      + '</div>';
   } else {
      var items = [];
      $.each(list, function (key, form) {
         // Build a HTML tile
         if (form.type == 'form') {
            url = rootDoc + '/plugins/formcreator/front/formdisplay.php?id=' + form.id;
         } else {
            url = rootDoc + '/front/knowbaseitem.form.php?id=' + form.id;
         }

         description = '';
         if (form.description) {
            description = '<div class="plugin_formcreator_formTile_description">'
                          +form.description
                          +'</div>';
         }

         items.push(
            '<div class="plugin_formcreator_formTile '+form.type+'" title="'+form.description+'">'
            + '<a href="' + url + '" class="plugin_formcreator_formTile_title">'
            + form.name
            + '</a>'
            + description
            + '</div>'
         );
      });

      // concatenate all HTML parts
      html = '<div id="plugin_formcreator_formlist">'
      + items.join("")
      + '</div>';

      return html;
   }
}

// === SEARCH BAR ===

// === QUESTIONS ===
var urlQuestion      = rootDoc + "/plugins/formcreator/ajax/question.php";
var urlFrontQuestion = rootDoc + "/plugins/formcreator/front/question.form.php";

function addQuestion(items_id, token, section) {
   modalWindow.load(urlQuestion, {
      section_id: section,
      form_id: items_id,
      _glpi_csrf_token: token
   }).dialog("open");
}

function editQuestion(items_id, token, question, section) {
   modalWindow.load(urlQuestion, {
      question_id: question,
      section_id: section,
      form_id: items_id,
      _glpi_csrf_token: token
   }).dialog("open");
}

function setRequired(token, question_id, val) {
   jQuery.ajax({
     url: urlFrontQuestion,
     type: "POST",
     data: {
         set_required: 1,
         id: question_id,
         value: val,
         _glpi_csrf_token: token
      }
   }).done(reloadTab);
}

function moveQuestion(token, question_id, action) {
   jQuery.ajax({
     url: urlFrontQuestion,
     type: "POST",
     data: {
         move: 1,
         id: question_id,
         way: action,
         _glpi_csrf_token: token
      }
   }).done(reloadTab);
}

function deleteQuestion(items_id, token, question_id) {
   if(confirm("<?php echo __('Are you sure you want to delete this question?', 'formcreator'); ?> ")) {
      jQuery.ajax({
        url: urlFrontQuestion,
        type: "POST",
        data: {
            id: question_id,
            delete_question: 1,
            plugin_formcreator_forms_id: items_id,
            _glpi_csrf_token: token
         }
      }).done(reloadTab);
   }
}


// === SECTIONS ===
var urlSection      = rootDoc + "/plugins/formcreator/ajax/section.php";
var urlFrontSection = rootDoc + "/plugins/formcreator/front/section.form.php";

function addSection(items_id, token) {
   modalWindow.load(urlSection, {
      form_id: items_id,
      _glpi_csrf_token: token
   }).dialog("open");
}

function editSection(items_id, token ,section) {
   modalWindow.load(urlSection, {
      section_id: section,
      form_id: items_id,
      _glpi_csrf_token: token
   }).dialog("open");
}

function deleteSection(items_id, token, section_id) {
   if(confirm("<?php echo __('Are you sure you want to delete this section?', 'formcreator'); ?> ")) {
      jQuery.ajax({
        url: urlFrontSection,
        type: "POST",
        data: {
            delete_section: 1,
            id: section_id,
            plugin_formcreator_forms_id: items_id,
            _glpi_csrf_token: token
         }
      }).done(reloadTab);
   }
}

function moveSection(token, section_id, action) {
   jQuery.ajax({
     url: urlFrontSection,
     type: "POST",
     data: {
         move: 1,
         id: section_id,
         way: action,
         _glpi_csrf_token: token
      }
   }).done(reloadTab);
}


// === TARGETS ===
function addTarget(items_id, token) {
   modalWindow.load(rootDoc + '/plugins/formcreator/ajax/target.php', {
      form_id: items_id,
      _glpi_csrf_token: token
   }).dialog("open");
}

function deleteTarget(items_id, token, target_id) {
   if(confirm("<?php echo __('Are you sure you want to delete this destination:', 'formcreator'); ?> ")) {
      jQuery.ajax({
        url: rootDoc + '/plugins/formcreator/front/target.form.php',
        type: "POST",
        data: {
            delete_target: 1,
            id: target_id,
            plugin_formcreator_forms_id: items_id,
            _glpi_csrf_token: token
         }
      }).done(reloadTab);

   }
}

// SHOW OR HIDE FORM FIELDS
var formcreatorQuestions = new Object();

function formcreatorChangeValueOf(field_id, value) {
   formcreatorQuestions[field_id] = value;
   formcreatorShowFields();
}
function formcreatorAddValueOf(field_id, value) {
   formcreatorQuestions[field_id] = value;
}

function formcreatorShowFields() {
   $.ajax({
      url: '../ajax/showfields.php',
      type: "POST",
      data: {
         values: JSON.stringify(formcreatorQuestions)
      }
   }).done(function(response){
      var questionToShow = JSON.parse(response);
      var i = 0;
      for (question in formcreatorQuestions) {
         if (questionToShow[question]) {
            $('#form-group-field' + question).show();
            i++;
            $('#form-group-field' + question).removeClass('line' + (i+1) % 2);
            $('#form-group-field' + question).addClass('line' + i%2);
         } else {
            $('#form-group-field' + question).hide();
            $('#form-group-field' + question).removeClass('line0');
            $('#form-group-field' + question).removeClass('line1');
         }
      }
   });
}

// DESTINATION
function formcreatorChangeDueDate(value) {
   $('#due_date_questions').hide();
   $('#due_date_time').hide();
   switch (value) {
      case 'answer' :
         $('#due_date_questions').show();
         break;
      case 'ticket' :
         $('#due_date_time').show();
         break;
      case 'calcul' :
         $('#due_date_questions').show();
         $('#due_date_time').show();
         break;
   }
}

function displayRequesterForm() {
   $('#form_add_requester').show();
   $('#btn_add_requester').hide();
   $('#btn_cancel_requester').show();
}

function hideRequesterForm() {
   $('#form_add_requester').hide();
   $('#btn_add_requester').show();
   $('#btn_cancel_requester').hide();
}

function displayWatcherForm() {
   $('#form_add_watcher').show();
   $('#btn_add_watcher').hide();
   $('#btn_cancel_watcher').show();
}

function hideWatcherForm() {
   $('#form_add_watcher').hide();
   $('#btn_add_watcher').show();
   $('#btn_cancel_watcher').hide();
}

function displayAssignedForm() {
   $('#form_add_assigned').show();
   $('#btn_add_assigned').hide();
   $('#btn_cancel_assigned').show();
}

function hideAssignedForm() {
   $('#form_add_assigned').hide();
   $('#btn_add_assigned').show();
   $('#btn_cancel_assigned').hide();
}

function formcreatorChangeActorRequester(value) {
   $('#block_requester_user').hide();
   $('#block_requester_group').hide();
   $('#block_requester_question_user').hide();
   $('#block_requester_question_group').hide();

   switch (value) {
      case 'person' :            $('#block_requester_user').show();           break;
      case 'question_person' :   $('#block_requester_question_user').show();  break;
      case 'group' :             $('#block_requester_group').show();          break;
      case 'question_group' :    $('#block_requester_question_group').show(); break;
   }
}

function formcreatorChangeActorWatcher(value) {
   $('#block_watcher_user').hide();
   $('#block_watcher_group').hide();
   $('#block_watcher_question_user').hide();
   $('#block_watcher_question_group').hide();

   switch (value) {
      case 'person' :            $('#block_watcher_user').show();             break;
      case 'question_person' :   $('#block_watcher_question_user').show();    break;
      case 'group' :             $('#block_watcher_group').show();            break;
      case 'question_group' :    $('#block_watcher_question_group').show();   break;
   }
}

function formcreatorChangeActorAssigned(value) {
   $('#block_assigned_user').hide();
   $('#block_assigned_group').hide();
   $('#block_assigned_question_user').hide();
   $('#block_assigned_question_group').hide();
   $('#block_assigned_supplier').hide();
   $('#block_assigned_question_supplier').hide();

   switch (value) {
      case 'person' :            $('#block_assigned_user').show();               break;
      case 'question_person' :   $('#block_assigned_question_user').show();      break;
      case 'group' :             $('#block_assigned_group').show();              break;
      case 'question_group' :    $('#block_assigned_question_group').show();     break;
      case 'supplier' :          $('#block_assigned_supplier').show();           break;
      case 'question_supplier' : $('#block_assigned_question_supplier').show();  break;
   }
}

