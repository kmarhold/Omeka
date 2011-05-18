<?php
/**
 * @version $Id$
 * @copyright Roy Rosenzweig Center for History and New Media, 2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @subpackage Forms
 * @access private
 **/

/**
 * Configuration form for theme options
 *
 * @internal This implements Omeka internals and is not part of the public API.
 * @access private
 * @package Omeka
 * @subpackage Forms
 * @copyright Roy Rosenzweig Center for History and New Media, 2010
 **/
class Omeka_Form_ThemeConfiguration extends Omeka_Form
{
    const THEME_FILE_HIDDEN_FIELD_NAME_PREFIX = 'hidden_file_';
    const MAX_UPLOAD_SIZE = '300kB';

    public static $allowedMimeTypes = array(
        'image/png',
        'image/gif',
        'image/jpeg',
        'image/jpg',
        'image/pjpeg'
    );

    public static $allowedExtensions = array(
        'png',
        'jpg',
        'jpeg',
        'gif'
    );

    protected $_themeName;
    protected $_themeOptions;
    
    public function init()
    {
        parent::init();
        $themeName = $this->getThemeName();
        
        $theme = Theme::getAvailable($themeName);
        $themeConfigIni = $theme->path . '/config.ini';

        if (file_exists($themeConfigIni) && is_readable($themeConfigIni)) {

            // get the theme configuration form specification
            $formElementsIni = new Zend_Config_Ini($themeConfigIni, 'config');
            $configIni = new Zend_Config(array('elements' => $formElementsIni));

            // create an omeka form from the configuration file
            $this->setConfig($configIni);
            $this->setAction('');
            $this->setAttrib('enctype', 'multipart/form-data');
            $this->setAttrib('class', 'theme-configuration');

            // add the 'Save Changes' submit button                      
            $this->addElement(
                'submit', 
                'submit', 
                array(
                    'label' => 'Save Changes',
                    'ignore' => true
                )
            );

            if (!($themeConfigValues = $this->getThemeOptions())) {
                $themeConfigValues = Theme::getOptions($themeName);
                $this->setThemeOptions($themeConfigValues);
            }
            
            // configure all of the form elements
            $elements = $this->getElements();
            foreach($elements as $element) {
                if ($element instanceof Zend_Form_Element_File) {
                    $this->_processFileElement($element);
                }
            }        

            // set all of the form element values            
            foreach($themeConfigValues as $key => $value) {
                if ($this->getElement($key)) {
                    $this->$key->setValue($value);
                }
            }
        }
    }
    
    public function setThemeName($themeName)
    {
        $this->_themeName = $themeName;
    }
    
    public function getThemeName()
    {
        return $this->_themeName;
    }

    public function setThemeOptions($themeOptions)
    {
        $this->_themeOptions = $themeOptions;
    }

    public function getThemeOptions()
    {
        return $this->_themeOptions;
    }

    /**
     * Add appropriate validators, filters, and hidden elements for  a file
     * upload element.
     *
     * @param Zend_Form_Element_File $element
     */
    private function _processFileElement($element)
    {
        $element->setDestination(Zend_Registry::get('storage')->getTempDir());

        $options = $this->getThemeOptions();
        $fileName = @$options[$element->getName()];
        if ($fileName) {
            $storage = Zend_Registry::get('storage');
            $fileUri = $storage->getUri($storage->getPathByType($fileName, 'theme_uploads'));
        } else {
            $fileUri = null;
        }

        // Add extension/mimetype filtering.
        if (get_option(File::DISABLE_DEFAULT_VALIDATION_OPTION) != '1') {
            $element->addValidator(new Omeka_Validate_File_Extension(self::$allowedExtensions));
            $element->addValidator(new Omeka_Validate_File_MimeType(self::$allowedMimeTypes));
            $element->addValidator(new Zend_Validate_File_Size(array('max' => self::MAX_UPLOAD_SIZE)));
        }

        // Make sure the file was uploaded before adding the Rename filter to the element
        if ($element->isUploaded()) {
            $this->_addFileRenameFilter($element);
        }

        // add a hidden field to store whether already exists
        $hiddenElement = new Zend_Form_Element_Hidden(self::THEME_FILE_HIDDEN_FIELD_NAME_PREFIX . $element->getName());
        $hiddenElement->setValue($fileUri);
        $hiddenElement->setDecorators(array('ViewHelper', 'Errors'));
        $hiddenElement->setIgnore(true);
        $this->addElement($hiddenElement);
    }

    /**
     * Add filter to rename uploaded files for themes.
     *
     * @param Zend_Form_Element_File $element
     */
    private function _addFileRenameFilter($element)
    {
        $elementName = $element->getName();
        $fileName = $element->getFileName(null, false);
        $uploadedFileName = Theme::getUploadedFileName($this->getThemeName(), $elementName, $fileName);
        $uploadedFilePath = $element->getDestination() . '/' . $uploadedFileName;
        $element->addFilter('Rename', array('target' => $uploadedFilePath, 'overwrite' => true));
    }
}
