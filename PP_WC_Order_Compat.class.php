<?php
class PP_WC_Order_Compat {
	
	private $order;
	
	public function __construct($order) {
		$this->order = $order;
	}
	
	public function __call($func, $args) {
		if (!method_exists($this->order, $func) && substr($func, 0, 4) == 'get_') {
			$prop = substr($func, 4);
			return $this->$prop;
		} else {
			return call_user_func_array(array($this->order, $func), $args);
		}
	}
	
	public static function __callStatic($func, $args) {
		return call_user_func_array(array('WC_Order', $func), $args);
	}
	
	public function __get($prop) {
		return $this->order->$prop;
	}
	
	public function __set($prop, $val) {
		$this->order->$prop = $val;
	}
	
	public function __isset($prop) {
		return isset($this->order->$prop);
	}
	
	public function __unset($prop) {
		unset($this->order->$prop);
	}
	
}
?>