<?php
/**
 * Tax Code Validator
 *
 * @class       WC_Facturadirecta_Tax_Code_Validator
 * @version     1.0.0
 * @category    Class
 * @author      OscarGare
 */
class WC_Facturadirecta_Tax_Code_Validator{
	
	/**
	 * Init
	 */ 
	public static function init(){
		add_filter( 'woocommerce_facturadirecta_es_tax_code_validate', array( __CLASS__, 'valid_tax_id_es' ), 10, 2 );
		add_filter( 'woocommerce_facturadirecta_es_is_b2b', array( __CLASS__, 'is_b2b_es' ), 10, 2 );
	}
	
	/**
	 * Validate ES TAX number 
	 */ 
	public static function valid_tax_id_es( $valid, $tax_code ){        
		return self::isValidIdNumber( $tax_code );
	}	
	
	/**
	 * Is B2B for ES
	 */ 
	public static function is_b2b_es( $is_b2b, $tax_code ){        
		$_tax_code = strtoupper( $tax_code );
		
		if ( substr( $_tax_code, 0, 2) == 'ES'  && strlen( $_tax_code ) > 3 ) {
			// EU VAT number validation
			$_tax_code = substr( $_tax_code, 2 );
		}
		return $is_b2b && self::isValidCIF( $_tax_code );		
	}
	
	/**
     *   This function validates a Spanish identification number
     *   verifying its check digits.
     *
     *   NIFs and NIEs are personal numbers.
     *   CIFs are corporates.
     *
     *   This function requires:
     *       - isValidCIF and isValidCIFFormat
     *       - isValidNIE and isValidNIEFormat
     *       - isValidNIF and isValidNIFFormat
     * 
     *   This function returns:
     *       TRUE: If specified identification number is correct
     *       FALSE: Otherwise
     *
     *   Usage:
     *       echo isValidIdNumber( 'G28667152' );
     *   Returns:
     *       TRUE
     */
    public static function isValidIdNumber( $docNumber ) {
        $fixedDocNumber = strtoupper( $docNumber );
		if ( substr( $fixedDocNumber, 0, 2) == 'ES'  && strlen( $fixedDocNumber ) > 3 ) {
			// EU VAT number validation
			$fixedDocNumber = substr( $fixedDocNumber, 2 );
		}
        return self::isValidNIF( $fixedDocNumber ) || self::isValidNIE( $fixedDocNumber ) || self::isValidCIF( $fixedDocNumber );
    }
	
	/*
    *   This function validates a Spanish identification number
    *   verifying its check digits.
    *
    *   This function is intended to work with NIF numbers.
    *
    *   This function is used by:
    *       - isValidIdNumber
    *
    *   This function requires:
    *       - isValidCIFFormat
    *       - getNIFCheckDigit
    *
    *   This function returns:
    *       TRUE: If specified identification number is correct
    *       FALSE: Otherwise
    *
    *   Algorithm works as described in:
    *       http://www.interior.gob.es/dni-8/calculo-del-digito-de-Check-del-nif-nie-2217
    *
    *   Usage:
    *       echo isValidNIF( '33576428Q' );
    *   Returns:
    *       TRUE
    */
    private static function isValidNIF( $docNumber ) {
        $isValid = FALSE;
        $fixedDocNumber = "";
        $correctDigit = "";
        $writtenDigit = "";
        if( !preg_match( "/^[A-Z]+$/i", substr( $fixedDocNumber, 1, 1 ) ) ) {
            $fixedDocNumber = strtoupper( substr( "000000000" . $docNumber, -9 ) );
        } else {
            $fixedDocNumber = strtoupper( $docNumber );
        }
        $writtenDigit = strtoupper(substr( $docNumber, -1, 1 ));
        if( self::isValidNIFFormat( $fixedDocNumber ) ) {
            $correctDigit = self::getNIFCheckDigit( $fixedDocNumber );
            if( $writtenDigit == $correctDigit ) {
                $isValid = TRUE;
            }
        }
        return $isValid;
    }

   /*
    *   This function validates a Spanish identification number
    *   verifying its check digits.
    *
    *   This function is intended to work with NIE numbers.
    *
    *   This function is used by:
    *       - isValidIdNumber
    *
    *   This function requires:
    *       - isValidNIEFormat
    *       - isValidNIF
    *
    *   This function returns:
    *       TRUE: If specified identification number is correct
    *       FALSE: Otherwise
    *
    *   Algorithm works as described in:
    *       http://www.interior.gob.es/dni-8/calculo-del-digito-de-control-del-nif-nie-2217
    *
    *   Usage:
    *       echo isValidNIE( 'X6089822C' )
    *   Returns:
    *       TRUE
    */
    private static function isValidNIE( $docNumber ) {
        $isValid = FALSE;
        $fixedDocNumber = "";
        if( !preg_match( "/^[A-Z]+$/i", substr( $fixedDocNumber, 1, 1 ) ) ) {
            $fixedDocNumber = strtoupper( substr( "000000000" . $docNumber, -9 ) );
        } else {
            $fixedDocNumber = strtoupper( $docNumber );
        }
        if( self::isValidNIEFormat( $fixedDocNumber ) ) {
            if( substr( $fixedDocNumber, 1, 1 ) == "T" ) {
                $isValid = TRUE;
            } else {
                /* The algorithm for validating the check digits of a NIE number is
                    identical to the altorithm for validating NIF numbers. We only have to
                    replace Y, X and Z with 1, 0 and 2 respectively; and then, run
                    the NIF altorithm */
                $numberWithoutLast = substr( $fixedDocNumber, 0, strlen($fixedDocNumber)-1 );
                $lastDigit = substr( $fixedDocNumber, strlen($fixedDocNumber)-1, strlen($fixedDocNumber) );
                $numberWithoutLast = str_replace('Y', '1', $numberWithoutLast);
                $numberWithoutLast = str_replace('X', '0', $numberWithoutLast);
                $numberWithoutLast = str_replace('Z', '2', $numberWithoutLast);
                $fixedDocNumber = $numberWithoutLast . $lastDigit;
                $isValid = self::isValidNIF( $fixedDocNumber );
            }
        }
        return $isValid;
    }
	
    /**
     *   This function validates a Spanish identification number
     *   verifying its check digits.
     *
     *   This function is intended to work with CIF numbers.
     *
     *   This function is used by:
     *       - isValidDoc
     *
     *   This function requires:
     *       - isValidCIFFormat
     *       - getCIFCheckDigit
     *
     *   This function returns:
     *       TRUE: If specified identification number is correct
     *       FALSE: Otherwise
     *
     * CIF numbers structure is defined at:
     *   BOE number 49. February 26th, 2008 (article 2)
     *
     *   Usage:
     *       echo isValidCIF( 'F43298256' );
     *   Returns:
     *       TRUE
     */
    private static function isValidCIF( $docNumber ) {
        $isValid = FALSE;
        $fixedDocNumber = "";
        $correctDigit = "";
        $writtenDigit = "";
        $fixedDocNumber = strtoupper( $docNumber );
        $writtenDigit = substr( $fixedDocNumber, -1, 1 );
        if( self::isValidCIFFormat( $fixedDocNumber ) == 1 ) {
            $correctDigit = self::getCIFCheckDigit( $fixedDocNumber );
            if( $writtenDigit == $correctDigit ) {
                $isValid = TRUE;
            }
        }
        return $isValid;
    }
	
    /*
     *   This function validates the format of a given string in order to
     *   see if it fits with NIF format. Practically, it performs a validation
     *   over a NIF, except this function does not check the check digit.
     *
     *   This function is intended to work with NIF numbers.
     * 
     *   This function is used by:
     *       - isValidIdNumber
     *       - isValidNIF
     *
     *   This function returns:
     *       TRUE: If specified string respects NIF format
     *       FALSE: Otherwise
     *
     *   Usage:
     *       echo isValidNIFFormat( '33576428Q' )
     *   Returns:
     *       TRUE
     */
    private static function isValidNIFFormat( $docNumber ) {
        return self::respectsDocPattern(
            $docNumber,
            '/^[KLM0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][a-zA-Z0-9]/' );
    }
    /*
     *   This function validates the format of a given string in order to
     *   see if it fits with NIE format. Practically, it performs a validation
     *   over a NIE, except this function does not check the check digit.
     *
     *   This function is intended to work with NIE numbers.
     *
     *   This function is used by:
     *       - isValidIdNumber
     *       - isValidNIE
     *
     *   This function requires:
     *       - respectsDocPattern
     *
     *   This function returns:
     *       TRUE: If specified string respects NIE format
     *       FALSE: Otherwise
     *
     *   Usage:
     *       echo isValidNIEFormat( 'X6089822C' )
     *   Returns:
     *       TRUE
     */
    private static function isValidNIEFormat( $docNumber ) {
        return self::respectsDocPattern(
            $docNumber,
            '/^[XYZT][0-9][0-9][0-9][0-9][0-9][0-9][0-9][A-Z0-9]/' );
    }
	
    /*
     *   This function validates the format of a given string in order to
     *   see if it fits with CIF format. Practically, it performs a validation
     *   over a CIF, but this function does not check the check digit.
     *
     *   This function is intended to work with CIF numbers.
     *
     *   This function is used by:
     *       - isValidIdNumber
     *       - isValidCIF
     *
     *   This function requires:
     *       - respectsDocPattern
     *
     *   This function returns:
     *       TRUE: If specified string respects CIF format
     *       FALSE: Otherwise
     *
     *   Usage:
     *       echo isValidCIFFormat( 'H24930836' )
     *   Returns:
     *       TRUE
     */
    private static function isValidCIFFormat( $docNumber ) {
        return
            (
            self::respectsDocPattern(
                $docNumber,
                '/^[PQSNWR][0-9][0-9][0-9][0-9][0-9][0-9][0-9][A-Z0-9]/' )
            ||
            self::respectsDocPattern(
                $docNumber,
                '/^[ABCDEFGHJUV][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]/' )
            )
            &&
            strlen($docNumber) === 9 ;
    }
	
    /**
     *   This function calculates the check digit for an individual Spanish
     *   identification number (NIF).
     *
     *   You can replace check digit with a zero when calling the function.
     *
     *   This function is used by:
     *       - isValidNIF
     *
     *   This function requires:
     *       - isValidNIFFormat
     *
     *   This function returns:
     *       - Returns check digit if provided string had a correct NIF structure
     *       - An empty string otherwise
     *
     *   Usage:
     *       echo getNIFCheckDigit( '335764280' )
     *   Returns:
     *       Q
     */
    private static function getNIFCheckDigit( $docNumber ) {
        $keyString = 'TRWAGMYFPDXBNJZSQVHLCKE';
        $fixedDocNumber = "";
        $position = 0;
        $writtenLetter = "";
        $correctLetter = "";
        if( !preg_match( "/^[A-Z]+$/i", substr( $fixedDocNumber, 1, 1 ) ) ) {
            $fixedDocNumber = strtoupper( substr( "000000000" . $docNumber, -9 ) );
        } else {
            $fixedDocNumber = strtoupper( $docNumber );
        }
        if( self::isValidNIFFormat( $fixedDocNumber ) ) {
            $writtenLetter = substr( $fixedDocNumber, -1 );
            if( self::isValidNIFFormat( $fixedDocNumber ) ) {
                $fixedDocNumber = str_replace( 'K', '0', $fixedDocNumber );
                $fixedDocNumber = str_replace( 'L', '0', $fixedDocNumber );
                $fixedDocNumber = str_replace( 'M', '0', $fixedDocNumber );
                $position = substr( $fixedDocNumber, 0, 8 ) % 23;
                $correctLetter = substr( $keyString, $position, 1 );
            }
        }
        return $correctLetter;
    }
	
    /**
     *   This function calculates the check digit for a corporate Spanish
     *   identification number (CIF).
     *
     *   You can replace check digit with a zero when calling the function.
     *
     *   This function is used by:
     *       - isValidCIF
     *
     *   This function requires:
     *     - isValidCIFFormat
     *
     *   This function returns:
     *       - The correct check digit if provided string had a
     *         correct CIF structure
     *       - An empty string otherwise
     *
     *   Usage:
     *       echo getCIFCheckDigit( 'H24930830' );
     *   Prints:
     *       6
     */
    private static function getCIFCheckDigit( $docNumber ) {
        $fixedDocNumber = "";
        $centralChars = "";
        $firstChar = "";
        $evenSum = 0;
        $oddSum = 0;
        $totalSum = 0;
        $lastDigitTotalSum = 0;
        $correctDigit = "";
        $fixedDocNumber = strtoupper( $docNumber );
        if( self::isValidCIFFormat( $fixedDocNumber ) ) {
            $firstChar = substr( $fixedDocNumber, 0, 1 );
            $centralChars = substr( $fixedDocNumber, 1, 7 );
            $evenSum =
                substr( $centralChars, 1, 1 ) +
                substr( $centralChars, 3, 1 ) +
                substr( $centralChars, 5, 1 );
            $oddSum =
                self::sumDigits( substr( $centralChars, 0, 1 ) * 2 ) +
                self::sumDigits( substr( $centralChars, 2, 1 ) * 2 ) +
                self::sumDigits( substr( $centralChars, 4, 1 ) * 2 ) +
                self::sumDigits( substr( $centralChars, 6, 1 ) * 2 );
            $totalSum = $evenSum + $oddSum;
            $lastDigitTotalSum = substr( $totalSum, -1 );
            if( $lastDigitTotalSum > 0 ) {
                $correctDigit = 10 - ( $lastDigitTotalSum % 10 );
            } else {
                $correctDigit = 0;
            }
        }
        /* If CIF number starts with P, Q, S, N, W or R,
            check digit sould be a letter */
        if( preg_match( '/[PQSNWR]/', $firstChar ) ) {
            $correctDigit = substr( "JABCDEFGHI", $correctDigit, 1 );
        }
        return $correctDigit;
    }
	
    /**
     *   This function validates the format of a given string in order to
     *   see if it fits a regexp pattern.
     *
     *   This function is intended to work with Spanish identification
     *   numbers, so it always checks string length (should be 9) and
     *   accepts the absence of leading zeros.
     *
     *   This function is used by:
     *       - isValidNIFFormat
     *       - isValidNIEFormat
     *       - isValidCIFFormat
     *
     *   This function returns:
     *       TRUE: If specified string respects the pattern
     *       FALSE: Otherwise
     *
     *   Usage:
     *       echo respectsDocPattern(
     *           '33576428Q',
     *           '/^[KLM0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][A-Z]/' );
     *   Returns:
     *       TRUE
     */
    private static function respectsDocPattern( $givenString, $pattern ) {
        $isValid = FALSE;
        $fixedString = strtoupper( $givenString );
        if( is_int( substr( $fixedString, 0, 1 ) ) ) {
            $fixedString = substr( "000000000" . $givenString , -9 );
        }
        if( preg_match( $pattern, $fixedString ) ) {
            $isValid = TRUE;
        }
        return $isValid;
    }
	
    /*
     *   This function performs the sum, one by one, of the digits
     *   in a given quantity.
     *
     *   For instance, it returns 6 for 123 (as it sums 1 + 2 + 3).
     *
     *   This function is used by:
     *       - getCIFCheckDigit
     *
     *   Usage:
     *       echo sumDigits( 12345 );
     *   Returns:
     *       15
     */
    private static function sumDigits( $digits ) {
        $total = 0;
        $i = 1;
        while( $i <= strlen( $digits ) ) {
            $thisNumber = substr( $digits, $i - 1, 1 );
            $total += $thisNumber;
            $i++;
        }
        return $total;
    }
	
	/**
	 *   This function obtains the description of a document type
	 *   for Spanish identification number.
	 *
	 *   For instance, if A83217281 is passed, it returns "Sociedad Anónima".
	 *
	 *   This function requires:
	 *       - identificationType (table)
	 *       - isValidCIFFormat
	 *       - isValidNIEFormat
	 *       - isValidNIFFormat
	 *
	 *   Usage:
	 *       echo getIdType( 'A49640873' )
	 *   Returns:
	 *       Sociedad Anónima
	 */    
    private static function getIdType( $docNumber ) {
        $identificationType = array(
        'K' => 'Español menor de catorce años o extranjero menor de dieciocho',
        'L' => 'Español mayor de catorce años residiendo en el extranjero',
        'M' => 'Extranjero mayor de dieciocho años sin NIE',
        '0' => 'Español con documento nacional de identidad',
        '1' => 'Español con documento nacional de identidad',
        '2' => 'Español con documento nacional de identidad',
        '3' => 'Español con documento nacional de identidad',
        '4' => 'Español con documento nacional de identidad',
        '5' => 'Español con documento nacional de identidad',
        '6' => 'Español con documento nacional de identidad',
        '7' => 'Español con documento nacional de identidad',
        '8' => 'Español con documento nacional de identidad',
        '9' => 'Español con documento nacional de identidad',
        'T' => 'Extranjero residente en España e identificado por la Policía con un NIE',
        'X' => 'Extranjero residente en España e identificado por la Policía con un NIE',
        'Y' => 'Extranjero residente en España e identificado por la Policía con un NIE',
        'Z' => 'Extranjero residente en España e identificado por la Policía con un NIE',
        /* As described in BOE number 49. February 26th, 2008 (article 3) */
        'A' => 'Sociedad Anónima',
        'B' => 'Sociedad de responsabilidad limitada',
        'C' => 'Sociedad colectiva',
        'D' => 'Sociedad comanditaria',
        'E' => 'Comunidad de bienes y herencias yacentes',
        'F' => 'Sociedad cooperativa',
        'G' => 'Asociación',
        'H' => 'Comunidad de propietarios en régimen de propiedad horizontal',
        'J' => 'Sociedad Civil => con o sin personalidad jurídica',
        'N' => 'Entidad extranjera',
        'P' => 'Corporación local',
        'Q' => 'Organismo público',
        'R' => 'Congregación o Institución Religiosa',
        'S' => 'Órgano de la Administración del Estado y Comunidades Autónomas',
        'U' => 'Unión Temporal de Empresas',
        'V' => 'Fondo de inversiones o de pensiones, agrupación de interés económico, etc',
        'W' => 'Establecimiento permanente de entidades no residentes en España' );
        $docTypeDescription = "";
        $firstChar = substr( $docNumber, 0, 1 );
        if( isValidNIFFormat( $docNumber ) or
            isValidNIEFormat( $docNumber ) or
            isValidCIFFormat( $docNumber ) ) {
            $docTypeDescription = $identificationType[ $firstChar ];
        }
        return $docTypeDescription;
    }
}

WC_Facturadirecta_Tax_Code_Validator::init();