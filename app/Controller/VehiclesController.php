
<?php
App::uses('AppController', 'Controller');
/**
 * Vehicles Controller
 *
 * @property Vehicle $Vehicle
 * @property PaginatorComponent $Paginator
 */
class VehiclesController extends AppController {

/**
 * Components
 *
 * @var array
 */
	public $components = array('Paginator', 'RequestHandler');

/**
 * Other Models
 * User
 */
    var $uses = array('Vehicle', 'Job', 'Driver', 'Location', 'Package', 'JobPackage', 'DriverLocation');

    public function beforeFilter(){
        parent::beforeFilter();
       $this->Auth->allow('getAllVehicles');
    }

/**
 * index method
 *
 * @return void
 */
	public function index() {
		$this->Vehicle->recursive = 0;
		$this->set('vehicles', $this->Paginator->paginate());

		$activeVehicles = $this->Vehicle->getActiveVehicles();
		$availableVehicles = $this->Vehicle->getAvailableVehicles();
		$unavailableVehicles = $this->Vehicle->getUnavailableVehicles();
 $activeDrivers = $this->Driver->getActiveDrivers();
		$availableDrivers = $this->Driver->getAvailableDrivers();
		$this->set('availableDrivers', $availableDrivers);

		$pendingJobs = $this->Job->getPendingJobs();
		$this->set('pendingJobs', $pendingJobs);

		$todaysDate = date('Y-m-d');

        $completedJobsToday = $this->Job->getCompletedJobsByDay($todaysDate);
        $this->set('completedJobsToday',$completedJobsToday);

		$this->set('activeVehicles', $activeVehicles);
		$this->set('availableVehicles', $availableVehicles);
		$this->set('unavailableVehicles', $unavailableVehicles);

		$activeVehicleLocations[] = array();

 foreach($activeDrivers as $activeDriver){
            $activeVehicleLocations[] = $this->DriverLocation->find('first', array(
                'conditions' => array('DriverLocation.driver_id' => $activeDriver['Driver']['id']),
                'order' => array('DriverLocation.date_time_stamp' => 'desc')
            ));
        }
        $this->set('activeVehicleLocations',$activeVehicleLocations);
		//Default Google Map Config
        $map_options = array(
            'id' => 'map_canvas',
            'width' => '100%',
            'height' => '900px',
            'style' => '',
            'zoom' => 6,
            'type' => 'ROADMAP',
            'custom' => null,
            'localize' => true,
            'marker' => false
        );

        $this->set('map_options', $map_options);
	}

	public function viewCurrentActiveJob($vehicleId){
		$activeJob = $this->Job->getActiveJobByVehicleId($vehicleId);
        $this->set('activeJob', $activeJob);
        $driverId = $activeJob[0]['Job']['driver_id'];
        $activeDrivers = $this->Driver->findAllById($driverId);
        $this->set('activeDrivers',$activeDrivers);

        $activeDriverLocations[] = array();

        foreach($activeDrivers as $activeDriver){
            $activeDriverLocations[] = $this->DriverLocation->find('first', array(
                'conditions' => array('DriverLocation.driver_id' => $activeDriver['Driver']['id']),
                'order' => array('DriverLocation.date_time_stamp' => 'desc')
            ));
        }
        
        $this->set('activeDriverLocations', $activeDriverLocations);

        $jobCollection = $this->Location->findAllById($activeJob[0]['Job']['collection_id']);
        $this->set('jobCollection',$jobCollection);
        $jobDropoff = $this->Location->findAllById($activeJob[0]['Job']['dropoff_id']);
        $this->set('jobDropoff', $jobDropoff);

        $vehicle = $this->Vehicle->findAllById($vehicleId);
        $this->set('vehicle', $vehicle);

        $packages[] = array();
        foreach($activeJob[0]['JobPackage'] as $jobPackage){
            $packages[] = $this->Package->findAllById($jobPackage['package_id']);
        }
        $this->set('packages', $packages);

        //Default Google Map Config
        $map_options = array(
            'id' => 'map_canvas',
            'width' => '100%',
            'height' => '900px',
            'style' => '',
            'zoom' => 6,
            'type' => 'ROADMAP',
            'custom' => null,
            'localize' => true,
            'marker' => false
        );

        $this->set('map_options', $map_options);
	}

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function view($id = null) {
		if (!$this->Vehicle->exists($id)) {
			throw new NotFoundException(__('Invalid vehicle'));
		}
		$options = array('conditions' => array('Vehicle.' . $this->Vehicle->primaryKey => $id));
		$this->set('vehicle', $this->Vehicle->find('first', $options));
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		if ($this->request->is('post')) {

			$this->Vehicle->create();
			$this->Vehicle->set('status', 'Inactive');

			if(isset($data['Vehicle']['crane']) != 'Yes'){ $this->Vehicle->set('crane', 'No'); }
			if(isset($data['Vehicle']['trailer']) != 'Yes'){ $this->Vehicle->set('trailer','No'); }
			if(isset($data['Vehicle']['hydraulic_beavertail']) != 'Yes'){ $this->Vehicle->set('hydraulic_beavertail', 'No'); }
			if(isset($data['Vehicle']['available']) != 'Yes'){ $this->Vehicle->set('available', 'No'); }

			if ($this->Vehicle->save($this->request->data)) {
				$this->Session->setFlash(__('The vehicle has been saved.'));
				return $this->redirect(array('action' => 'manage'));
			} 
			else {
				$this->Session->setFlash(__('The vehicle could not be saved. Please, try again.'));
			}
		}
	}

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function edit($id = null) {
		if (!$this->Vehicle->exists($id)) {
			throw new NotFoundException(__('Invalid vehicle'));
		}
		if ($this->request->is(array('post', 'put'))) {
			if ($this->Vehicle->save($this->request->data)) {
				$this->Session->setFlash(__('The vehicle has been saved.'));
				return $this->redirect(array('action' => 'manage'));
			} else {
				$this->Session->setFlash(__('The vehicle could not be saved. Please, try again.'));
			}
		} else {
			$options = array('conditions' => array('Vehicle.' . $this->Vehicle->primaryKey => $id));
			$this->request->data = $this->Vehicle->find('first', $options);
		}

		$vehicleTypes = $this->Vehicle->VehicleType->find('list');
		$this->set(compact('vehicleTypes'));
		$licenseTypes = $this->Vehicle->LicenseType->find('list',array('id','license_type'));
		$this->set('licenseTypes',$licenseTypes);
	}

/**
 * delete method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function delete($id = null) {
		$this->Vehicle->id = $id;
		if (!$this->Vehicle->exists()) {
			throw new NotFoundException(__('Invalid vehicle'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->Vehicle->delete()) {
			$this->Session->setFlash(__('The vehicle has been deleted.'));
		} else {
			$this->Session->setFlash(__('The vehicle could not be deleted. Please, try again.'));
		}
		return $this->redirect(array('action' => 'manage'));
	}

	public function manage(){
		$this->set('vehicles', $this->Vehicle->find('all'));

     	$licenseTypes = $this->Vehicle->LicenseType->find('list', array('id','license_type'));
     	$this->set('licenseTypes', $licenseTypes);

     	$vehicleTypes = $this->Vehicle->VehicleType->find('list', array('id','vehicle_type'));
     	$this->set('vehicleTypes', $vehicleTypes);
	}


     public function getAllVehicles(){

     	if($this->request->is('post')){
     		$key = $this->request->data['key'];

     		$vehicles = $this->Vehicle->find('all');
     		
     		$message = $vehicles;
     		if($message == ""){
     			$message = "No vehicles found.";
     		}
     	}
     	else  {
     		$message = "You are not authorized for this page.";
     	}
     	$this->set('message', $message);
     	$this->set('_serialize', array('message'));
     }

     public function updateVehicleById(){
     	    if($this->request->data['key'] == "9c36c7108a73324100bc9305f581979071d45ee9"){
            $this->Vehicle->id = $this->request->data['vehicle_id'];
            $this->Driver->id = $this->request->data['driver_id'];
            if ($this->Vehicle->save($this->request->data)) {
                $message = 'Vehicle Updated';
                $log = $this->request->data['vehicle_name'] . ' is now ' . $this->request->data['available'];
                $this->UpdateLog->set('log',$log);
                $this->UpdateLog->set('driver_id',$this->Driver->id);
                $this->UpdateLog->save();
            } else {
                $message = 'Error';
            }

        }
        else {
            $message = 'Authentication Needed';
        }
        $this->set('message', $message);
        $this->set('_serialize', array('message'));
     }
}
