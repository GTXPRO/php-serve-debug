<?php
namespace QTCS\Files;

use SplFileInfo;

class File extends SplFileInfo {

	public function getSize()
	{
		if (is_null($this->size))
		{
			$this->size = parent::getSize();
		}

		return $this->size;
	}
}