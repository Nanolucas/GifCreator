<?php

namespace GifCreator;

/**
 * Create an animated GIF from multiple images
 * 
 * @version 1.1
 * @link https://github.com/Nanolucas/GifCreator
 * @author Sybio (Clément Guillemain  / @Sybio01)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @copyright Clément Guillemain
 */
class GifCreator {
    /**
     * @var string The gif string source
     */
    private $gif;
    
    /**
     * @var string Encoder version
     */
	private $version;
    
    /**
     * @var boolean Check the image is built or not
     */
    private $image_built;

    /**
     * @var array Frames string sources
     */
	private $frame_sources;
    
    /**
     * @var integer Gif loop
     */
	private $loop;
    
    /**
     * @var integer Gif dis
     */
	private $dis;
    
    /**
     * @var integer Gif color
     */
	private $colour;
    
    /**
     * @var array
     */
	private $errors;
 
    // Methods
    // ===================================================================================
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->reset();
        
        // Static data
        $this->version = 'GifCreator';
        $this->errors = array(
            'ERR00' => 'Input must be an array of frame images and corresponding durations',
    		'ERR01' => 'Source is not a GIF image.',
    		'ERR02' => 'You have to give resource image variables, image URLs or image binary sources in $frames array.',
    		'ERR03' => 'Animation cannot be made from animated GIF source.',
        );
    }

	/**
     * Create the GIF string
     * 
     * @param array $frames An array of frame: can be file paths, resource image variables, binary sources or image URLs
     * @param array $durations An array containing the duration of each frame
     * @param integer $loop Number of GIF loops before stopping animation (Set 0 to get an infinite loop)
     * 
     * @return string The GIF string source
     */
	public function create($frames = array(), $durations = array(), $loop = 0) {
		if (!is_array($frames) && !is_array($durations)) {
            throw new \Exception($this->version.': ' . $this->errors['ERR00']);
		}
        
		$this->loop = ($loop > -1) ? $loop : 0;
		$this->dis = 2;
        
		for ($i = 0; $i < count($frames); $i++) {
			// Resource var of GDImage
			if (is_resource($frames[$i]) || $frames[$i] instanceof \GdImage) {
                $resourceImg = $frames[$i];
                
                ob_start();
                imagegif($frames[$i]);
                $this->frame_sources[] = ob_get_contents();
                ob_end_clean();
			// File path or URL or Binary source code
            } elseif (is_string($frames[$i])) {
				// File path validity check
                if (file_exists($frames[$i]) || filter_var($frames[$i], FILTER_VALIDATE_URL)) {
                    $frames[$i] = file_get_contents($frames[$i]);                    
                }
                
                $resourceImg = imagecreatefromstring($frames[$i]);
                
                ob_start();
                imagegif($resourceImg);
                $this->frame_sources[] = ob_get_contents();
                ob_end_clean();
			// Invalid type
			} else {
                throw new \Exception($this->version.': ' . $this->errors['ERR02']);
			}
            
            if ($i == 0) {
                $colour = imagecolortransparent($resourceImg);
            }
            
			if (substr($this->frame_sources[$i], 0, 6) != 'GIF87a' && substr($this->frame_sources[$i], 0, 6) != 'GIF89a') {
                throw new \Exception($this->version.': ' . $i.' ' . $this->errors['ERR01']);
			}
            
			for ($j = (13 + 3 * (2 << (ord($this->frame_sources[$i][10]) & 0x07))), $k = true; $k; $j++) {
				switch ($this->frame_sources[$i][$j]) {
					case '!':
						if ((substr($this->frame_sources[$i], ($j + 3), 8)) == 'NETSCAPE') {
                            throw new \Exception($this->version . ': ' . $this->errors['ERR03'].' ('.($i + 1).' source).');
						}
						break;
					case ';':
						$k = false;
						break;
				}
			}
            
            unset($resourceImg);
		}
		
        if (isset($colour)) {
            $this->colour = $colour;
        } else {
            $red = $green = $blue = 0;
            $this->colour = ($red > -1 && $green > -1 && $blue > -1) ? ($red | ($green << 8) | ($blue << 16)) : -1;
        }
        
		$this->gifAddHeader();
        
		for ($i = 0; $i < count($this->frame_sources); $i++) {
			$this->addGifFrames($i, $durations[$i]);
		}
        
		$this->gifAddFooter();
        
        return $this->gif;
	}
    
    // Internals
    // ===================================================================================
    
	/**
     * Add the header gif string in its source
     */
	public function gifAddHeader() {
		$cmap = 0;

		if (ord($this->frame_sources[0][10]) & 0x80) {
			$cmap = 3 * (2 << (ord($this->frame_sources[0][10]) & 0x07));

			$this->gif .= substr($this->frame_sources[0], 6, 7);
			$this->gif .= substr($this->frame_sources[0], 13, $cmap);
			$this->gif .= "!\377\13NETSCAPE2.0\3\1" . $this->encodeAsciiToChar($this->loop)."\0";
		}
	}
    
	/**
     * Add the frame sources to the GIF string
     * 
     * @param integer $i
     * @param integer $d
     */
	public function addGifFrames($i, $d) {
		$locals_str = 13 + 3 * (2 << (ord($this->frame_sources[ $i ][10]) & 0x07));

		$locals_end = strlen($this->frame_sources[$i]) - $locals_str - 1;
		$locals_tmp = substr($this->frame_sources[$i], $locals_str, $locals_end);

		$global_len = 2 << (ord($this->frame_sources[0 ][10]) & 0x07);
		$locals_len = 2 << (ord($this->frame_sources[$i][10]) & 0x07);

		$global_rgb = substr($this->frame_sources[0], 13, 3 * (2 << (ord($this->frame_sources[0][10]) & 0x07)));
		$locals_rgb = substr($this->frame_sources[$i], 13, 3 * (2 << (ord($this->frame_sources[$i][10]) & 0x07)));

		$locals_ext = "!\xF9\x04".chr(($this->dis << 2) + 0).chr(($d >> 0 ) & 0xFF).chr(($d >> 8) & 0xFF)."\x0\x0";

		if ($this->colour > -1 && ord($this->frame_sources[$i][10]) & 0x80) {
			for ($j = 0; $j < (2 << (ord($this->frame_sources[$i][10] ) & 0x07)); $j++) {
				if (ord($locals_rgb[3 * $j + 0]) == (($this->colour >> 16) & 0xFF) &&
					ord($locals_rgb[3 * $j + 1]) == (($this->colour >> 8) & 0xFF) &&
					ord($locals_rgb[3 * $j + 2]) == (($this->colour >> 0) & 0xFF)
				) {
					$locals_ext = "!\xF9\x04".chr(($this->dis << 2) + 1).chr(($d >> 0) & 0xFF).chr(($d >> 8) & 0xFF).chr($j)."\x0";
					break;
				}
			}
		}
        
		switch ($locals_tmp[0]) {
			case '!':
				$locals_img = substr($locals_tmp, 8, 10);
				$locals_tmp = substr($locals_tmp, 18, strlen($locals_tmp) - 18);       
				break;
			case ',':
				$locals_img = substr($locals_tmp, 0, 10);
				$locals_tmp = substr($locals_tmp, 10, strlen($locals_tmp) - 10);            
				break;
		}
        
		if (ord($this->frame_sources[$i][10]) & 0x80 && $this->image_built) {
			if ($global_len == $locals_len) {
				if ($this->gifBlockCompare($global_rgb, $locals_rgb, $global_len)) {
					$this->gif .= $locals_ext . $locals_img . $locals_tmp;
				} else {
					$byte = ord($locals_img[9]);
					$byte |= 0x80;
					$byte &= 0xF8;
					$byte |= (ord($this->frame_sources[0][10]) & 0x07);
					$locals_img[9] = chr($byte);
					$this->gif .= $locals_ext . $locals_img . $locals_rgb . $locals_tmp;
				}
			} else {
				$byte = ord($locals_img[9]);
				$byte |= 0x80;
				$byte &= 0xF8;
				$byte |= (ord($this->frame_sources[$i][10]) & 0x07);
				$locals_img[9] = chr($byte);
				$this->gif .= $locals_ext . $locals_img . $locals_rgb . $locals_tmp;
			}
		} else {
			$this->gif .= $locals_ext . $locals_img . $locals_tmp;
		}
        
		$this->image_built = true;
	}
    
	/**
     * Add the gif string footer char
     */
	public function gifAddFooter() {
		$this->gif .= ';';
	}
    
	/**
     * Compare two block and return the version
     * 
     * @param string $global_block
     * @param string $local_block
     * @param integer $length
     * 
     * @return integer
	 */
	public function gifBlockCompare($global_block, $local_block, $length) {
		for ($i = 0; $i < $length; $i++) {
		  
			if ($global_block[3 * $i + 0] != $local_block[3 * $i + 0] ||
				$global_block[3 * $i + 1] != $local_block[3 * $i + 1] ||
				$global_block[3 * $i + 2] != $local_block[3 * $i + 2]) {
				
                return 0;
			}
		}

		return 1;
	}
    
	/**
     * Encode an ASCII char into a string char
     * 
     * $param integer $char ASCII char
     * 
     * @return string
	 */
	public function encodeAsciiToChar($char) {
		return (chr($char & 0xFF).chr(($char >> 8) & 0xFF));
	}
    
    /**
     * Reset and clean the current object
     */
    public function reset() {
        $this->frame_sources = [];
        $this->gif = 'GIF89a'; // the GIF header
        $this->image_built = false;
        $this->loop = 0;
        $this->dis = 2;
        $this->colour = -1;
    }
    
    // Getter / Setter
    // ===================================================================================

	/**
     * Get the final GIF image string
     * 
     * @return string
	 */
	public function getGif() {
		return $this->gif;
	}
}