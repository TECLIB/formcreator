<?php
class TargetTicketTest extends SuperAdminTestCase {

   public function setUp() {
      parent::setUp();

      $this->formData = array(
            'entities_id'           => $_SESSION['glpiactive_entity'],
            'name'                  => 'a form',
            'description'           => 'form description',
            'content'               => 'a content',
            'is_active'             => 1,
            'validation_required'   => 0
      );

      $this->target = array(
            'name'                  => 'a target',
            'itemtype'              => 'PluginFormcreatorTargetTicket'
      );
   }

   public function testInitCreateForm() {
      $form = new PluginFormcreatorForm();
      $form->add($this->formData);
      $this->assertFalse($form->isNewItem());

      return $form;
   }

   /**
    * @depends testInitCreateForm
    * @param PluginFormcreatorForm $form
    */
   public function testCreateTarget(PluginFormcreatorForm $form) {
      $target = new PluginFormcreatorTarget();
      $this->target = $this->target + array(
            'plugin_formcreator_forms_id' => $form->getID()
      );
      $target->add($this->target);
      $this->assertFalse($target->isNewItem());
      $this->assertEquals($form->getID(), $target->getField('plugin_formcreator_forms_id'));
      $this->assertEquals('PluginFormcreatorTargetTicket', $target->getField('itemtype'));

      return $target;
   }

   /**
    * @depends testInitCreateForm
    * @depends testCreateTarget
    * @param PluginFormcreatorForm $form
    * @param PluginFormcreatorTarget $target
    */
   public function testTargetTicket(PluginFormcreatorForm $form, PluginFormcreatorTarget $target) {
      $targetTicket = $target->getField('items_id');
      $targetTicket = new PluginFormcreatorTargetTicket();
      $targetTicket->getFromDB($target->getField('items_id'));
      $this->assertFalse($targetTicket->isNewItem());
      $this->assertEquals($target->getField('name'), $targetTicket->getField('name'));

      return $targetTicket;
   }

   /**
    * @depends testTargetTicket
    * @param PluginFormcreatorTargetTicket $target
    */
   public function testTargetTicketActors(PluginFormcreatorTargetTicket $targetTicket) {
      $requesterActor = new PluginFormcreatorTargetTicket_Actor();
      $observerActor = new PluginFormcreatorTargetTicket_Actor();
      $targetTicketId = $targetTicket->getID();

      $requesterActor->getFromDBByQuery("WHERE `plugin_formcreator_targettickets_id` = '$targetTicketId'
            AND `actor_role` = 'requester' AND `actor_type` = 'creator'");
      $observerActor->getFromDBByQuery("WHERE `plugin_formcreator_targettickets_id` = '$targetTicketId'
            AND `actor_role` = 'observer' AND `actor_type` = 'validator'");

      $this->assertFalse($requesterActor->isNewItem());
      $this->assertFalse($observerActor->isNewItem());
      $this->assertEquals(1, $requesterActor->getField('use_notification'));
      $this->assertEquals(1, $observerActor->getField('use_notification'));
   }

}