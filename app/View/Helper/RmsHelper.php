<?php
/**
 * RMS Helper
 *
 * The RMS helper adds useful functions for making use of the RMS JavaScript library.
 *
 * @author		Russell Toris - rctoris@wpi.edu
 * @copyright	2014 Worcester Polytechnic Institute
 * @link		https://github.com/WPI-RAIL/rms
 * @since		RMS v 2.0.0
 * @version		2.0.0
 * @package		app.View.Helper
 */
class RmsHelper extends Helper {

	/**
	 * Create a span with a status icon on if the rosbridge server is up and running.
	 *
	 * @param string $protocol The rosbridge protocol ('ws' or 'wss')
	 * @param string $host The rosbridge host.
	 * @param int $port The rosbridge port.
	 * @return string The HTML for the span.
	 */
	public function rosbridgeStatus($protocol, $host, $port) {
		// random span id
		$id = rand();

		// default spinner
		$html = __('<span id="rosbridge-%d">', $id);
		$html .= '<span class="icon orange fa-spinner"></span>';
		$html .= '</span>';

		// check the connection via RMS JavaScript
		$html .= '<script>';
		$html .= __('RMS.verifyRosbridge("%s", "%s", %d, "rosbridge-%d");', h($protocol), h($host), h($port), $id);
		$html .= '</script>';

		return $html;
	}

	/**
	 * Create a section with a rosbridge diagnostic panel.
	 *
	 * @param string $protocol The rosbridge protocol ('ws' or 'wss')
	 * @param string $host The rosbridge host.
	 * @param int $port The rosbridge port.
	 * @return string The HTML for the section.
	 */
	public function rosbridgePanel($protocol, $host, $port) {
		// random span id
		$id = rand();

		// default spinner
		$html = __('<section id="rosbridge-panel-%d"  class="center">', $id);
		$html .= '<h2>Acquiring connection... <span class="icon orange fa-spinner"></span></h2>';
		$html .= '</section>';

		// generate via RMS JavaScript
		$html .= '<script>';
		$html .= __(
			'RMS.generateRosbridgeDiagnosticPanel("%s", "%s", %d, "rosbridge-panel-%d");',
			h($protocol),
			h($host),
			h($port),
			$id
		);
		$html .= '</script>';

		return $html;
	}

	/**
	 * Create a span with a status icon on if the MJPEG server is up and running.
	 *
	 * @param string $host The MJPEG host.
	 * @param int $port The MJPEG port.
	 * @return string The HTML for the span.
	 */
	public function mjpegServerStatus($host, $port) {
		// random span id
		$id = rand();

		// default spinner
		$html = __('<span id="mjpeg-%d">', $id);
		$html .= '<span class="icon orange fa-spinner"></span>';
		$html .= '</span>';

		// check the connection via RMS JavaScript
		$html .= '<script>';
		$html .= __('RMS.verifyMjpegServer("%s", %d, "mjpeg-%d");', h($host), h($port), $id);
		$html .= '</script>';

		return $html;
	}

	/**
	 * Create a section with a MJPEG server diagnostic panel.
	 *
	 * @param string $host The MJPEG host.
	 * @param int $port The MJPEG port.
	 * @return string The HTML for the section.
	 */
	public function mjpegPanel($host, $port, $topics) {
		// random span id
		$id = rand();

		// default spinner
		$html = __('<section id="mjpeg-panel-%d"  class="center">', $id);
		$html .= '<h2>Acquiring connection... <span class="icon orange fa-spinner"></span></h2>';
		$html .= '</section>';

		// generate via RMS JavaScript
		$html .= '<script>';
		$html .= 'var topics = [];';
		foreach ($topics as $topic) {
			$html .= __('topics.push("%s");', h($topic));
		}
		$html .= __(
			'RMS.generateMjpegDiagnosticPanel("%s", %d, topics, "mjpeg-panel-%d");',
			h($host),
			h($port),
			$id
		);
		$html .= '</script>';

		return $html;
	}

	/**
	 * Create a section with a MJPEG stream display.
	 *
	 * @param string $host The MJPEG host.
	 * @param int $port The MJPEG port.
	 * @param string $topic The MJPEG stream topic.
	 * @param array|null $options The stream options.
	 * @return string The HTML for the section.
	 */
	public function mjpegStream($host, $port, $topic, $options = null) {
		// random span id
		$id = rand();

		// default spinner
		$html = __('<section id="mjpeg-stream-%d"  class="center">', $id);
		$html .= '<h2>Acquiring connection... <span class="icon orange fa-spinner"></span></h2>';
		$html .= '</section>';

		// parse the options
		$optionsJson = '{}';
		if (isset($options) && $options) {
			$optionsJson = '{';
			$optionsJson .= 'width:';
			$optionsJson .= ($options['width']) ? h($options['width']) : 'null';
			$optionsJson .= ',height:';
			$optionsJson .= ($options['height']) ? h($options['height']) : 'null';
			$optionsJson .= ',quality:';
			$optionsJson .= ($options['quality']) ? h($options['quality']) : 'null';
			$optionsJson .= ',invert:';
			$optionsJson .= ($options['invert']) ? 'true' : 'false';
			$optionsJson .= '}';
		}

		// generate via RMS JavaScript
		$html .= '<script>';
		$html .= __(
			'RMS.generateStream("%s", %d, "%s", "mjpeg-stream-%d", %s);',
			h($host),
			h($port),
			h($topic),
			$id,
			$optionsJson
		);
		$html .= '</script>';

		return $html;
	}
}