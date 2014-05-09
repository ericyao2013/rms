<?php
App::uses('HttpSocket', 'Network/Http');

/**
 * MJPEG Servers Controller
 *
 * A MJPEG server contains information about the host and port.
 *
 * @author		Russell Toris - rctoris@wpi.edu
 * @copyright	2014 Worcester Polytechnic Institute
 * @link		https://github.com/WPI-RAIL/rms
 * @since		RMS v 2.0.0
 * @version		2.0.0
 * @package		app.Controller
 */
class MjpegsController extends AppController {

	/**
	 * The used helpers for the controller.
	 *
	 * @var array
	 */
	public $helpers = array('Html', 'Form');

	/**
	 * The used components for the controller.
	 *
	 * @var array
	 */
	public $components = array('Session', 'Auth' => array('authorize' => 'Controller'));

	/**
	 * The admin index action lists information about all environments. This allows the admin to add, edit, or delete
	 * entries.
	 */
	public function admin_index() {
		// grab all the entries
		$mjpegs = $this->Mjpeg->find('all');
		$mjpegsEdit = array();
		// check the connection
		$http = new HttpSocket(array('timeout' => 1));
		foreach ($mjpegs as $mjpeg) {
			$uri = 'http://' . $mjpeg['Mjpeg']['host'] . ':' . $mjpeg['Mjpeg']['port'] . '/';
			try {
				$get = $http->get($uri);
				$mjpeg['Mjpeg']['status'] = strlen($get) === 0;
			} catch (Exception $e) {
				$mjpeg['Mjpeg']['status'] = false;
			}
			$mjpegsEdit[] = $mjpeg;
		}
		$this->set('mjpegs', $mjpegsEdit);
	}

	/**
	 * The admin add action. This will allow the admin to create a new entry.
	 */
	public function admin_add() {
		// only work for POST requests
		if ($this->request->is('post')) {
			// create a new entry
			$this->Mjpeg->create();
			// set the current timestamp for creation and modification
			$this->Mjpeg->data['Mjpeg']['created'] = date('Y-m-d H:i:s');
			$this->Mjpeg->data['Mjpeg']['modified'] = date('Y-m-d H:i:s');
			// attempt to save the entry
			if ($this->Mjpeg->save($this->request->data)) {
				$this->Session->setFlash('The Mjpeg server has been saved.');
				return $this->redirect(array('action' => 'index'));
			}
			$this->Session->setFlash('Unable to add the MJPEG server.');
		}

		$this->set('title_for_layout', 'Add MJPEG Server');
	}

	/**
	 * The admin edit action. This allows the admin to edit an existing entry.
	 *
	 * @param int $id The ID of the entry to edit.
	 * @throws NotFoundException Thrown if an entry with the given ID is not found.
	 */
	public function admin_edit($id = null) {
		if (!$id) {
			// no ID provided
			throw new NotFoundException('Invalid mjpeg.');
		}

		$mjpeg = $this->Mjpeg->findById($id);
		if (!$mjpeg) {
			// no valid entry found for the given ID
			throw new NotFoundException('Invalid mjpeg.');
		}

		// only work for PUT requests
		if ($this->request->is(array('mjpeg', 'put'))) {
			// set the ID
			$this->Mjpeg->id = $id;
			// set the current timestamp for modification
			$this->Mjpeg->data['Mjpeg']['modified'] = date('Y-m-d H:i:s');
			// attempt to save the entry
			if ($this->Mjpeg->save($this->request->data)) {
				$this->Session->setFlash('The MJPEG server has been updated.');
				return $this->redirect(array('action' => 'index'));
			}
			$this->Session->setFlash('Unable to update the MJPEG server.');
		}

		// store the entry data if it was not a PUT request
		if (!$this->request->data) {
			$this->request->data = $mjpeg;
		}

		$this->set('title_for_layout', __('Edit MJPEG Server - %s', $mjpeg['Mjpeg']['name']));
	}

	/**
	 * The admin delete action. This allows the admin to delete an existing entry.
	 *
	 * @param int $id The ID of the entry to delete.
	 * @throws MethodNotAllowedException Thrown if a GET request is made.
	 */
	public function admin_delete($id = null) {
		// do not allow GET requests
		if ($this->request->is('get')) {
			throw new MethodNotAllowedException();
		}

		// attempt to delete the entry
		if ($this->Mjpeg->delete($id)) {
			$this->Session->setFlash('The MJPEG server has been deleted.');
			return $this->redirect(array('action' => 'index'));
		}
	}
}
