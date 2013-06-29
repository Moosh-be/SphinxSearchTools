<?php
/**
 *
 * Classe pour créer une query string à passer à sphinxSE ou pour faire une query directe
 *
 * Cette classe sert bien à créer une query.
 *
 */
/**
 *
 * This class build a query for sphinx
 *
 * @author moosh-be
 *
 */

class Search_Sphinx_Query {

    const SPH_GROUPBY_DAY = 'day'; // extracts year, month and day in YYYYMMDD format from timestamp;
    const SPH_GROUPBY_WEEK = 'week'; //, extracts year and first day of the week number (counting from year start) in YYYYNNN format from timestamp;
    const SPH_GROUPBY_MONTH = 'month'; // extracts month in YYYYMM format from timestamp;
    const SPH_GROUPBY_YEAR = 'year'; // extracts year in YYYY format from timestamp;
    const SPH_GROUPBY_ATTR = 'attr'; // uses attribute value itself for grouping.
    const SPH_GROUPBY_ATTRPAIR = 'attrpair'; // uses attribute value itself for grouping.

    const SEARCH_MODE_ALL = 'all'; // Recherche tous les mots de la requête (mode par défaut).
    const SEARCH_MODE_ANY = 'any'; // Recherche n'importe quel mot de la requête.
    const SEARCH_MODE_PHRASE = 'phrase'; // Recherche la requête comme une phrase.
    const SEARCH_MODE_BOOL = 'boolean'; //Recherche la requête comme une expression booléenne.
    const SEARCH_MODE_EXTENDED = 'extended'; // Recherche la requête comme une expression Sphinx, dans son langage interne.
    const SEARCH_MODE_FULLSCAN = 'fullscan'; // Active la recherche intégrale.
    const SEARCH_MODE_EXTENDED2 = 'extended2'; // Identique é SPH_MATCH_EXTENDED avec le classement et la recherche par quorum en plus.

    const SPH_SORT_RELEVANCE = 'relevance'; // Tri par pertinence, en ordre décroissant (meilleures occurences en premier).
    const SPH_SORT_ATTR_DESC = 'attr_desc'; // Tri par un attribut, en ordre décroissant (plus grandes valeurs en premier).
    const SPH_SORT_ATTR_ASC = 'attr_asc'; // Tri par un attribut, en ordre croissant (plus petites valeurs en premier).
    const SPH_SORT_TIME_SEGMENTS = 'time_segments'; // Tri par segment de temps (derniére heure, jour, semaine, mois), en ordre descendant, et par pertinence en ordre décroissant.
    const SPH_SORT_EXTENDED = 'extended'; // Tri par combinaison de colonnes, comme en SQL.
    const SPH_SORT_EXPR = 'SPH_SORT_EXPR'; // Tri par expression arithmétique.

    /**
     * #@+
     *
     * Valid values for rankmode
     *
     * @see http://sphinxsearch.com/docs/2.0.6/weighting.html
     * @see http://sphinxsearch.com/blog/2010/08/17/how-sphinx-relevance-ranking-works/
     * @see Search_Sphinx_Query::getRankMode() to check current rank mode
     * @see Search_Sphinx_Query::setRankMode() to set rank mode
     *
     * Previously, this ranking function was rigidly bound to the matching mode.
     * SPH_MATCH_ALL uses SPH_RANK_PROXIMITY ranker;
     * SPH_MATCH_ANY uses SPH_RANK_MATCHANY ranker;
     * SPH_MATCH_PHRASE uses SPH_RANK_PROXIMITY ranker;
     * SPH_MATCH_BOOLEAN uses SPH_RANK_NONE ranker.
     *
     */

    /**
     * Désactive le classement.
     * *Ce mode est le plus rapide.*
     * Il est essentiellement équivalent à une recherche booléenne,
     * avec un poids de 1 associé à chaque occurrence.
     * @type string
     */
    const SPH_RANK_NONE = 'SPH_RANK_NONE';

    /**
     * Mode de classement par défaut, avec un calcul de proximité et un tri
     * BM25.
     *
     * SPH_RANK_PROXIMITY_BM25, the default SphinxQL ranker
     * and also the default ranker when “extended”
     * matching mode is used with SphinxAPI, computes weight as
     * <code>
     * weight = doc_phrase_weight*1000 + integer(doc_bm25*999)
     * </code>
     *
     * So document phrase proximity is the primary factor and BM25 is an
     * auxiliary one that additionally sorts documents sharing the same phrase
     * proximity.
     *
     * BM25 belongs to 0..1 range, so last 3 decimal digits of final
     * weight contain scaled BM25, and all the other digits are used for the
     * phrase weight.
     *
     * @type string
     */
    const SPH_RANK_PROXIMITY_BM25 = 'SPH_RANK_PROXIMITY_BM25';

    /**
     * SPH_RANK_WORDCOUNT ranker counts all the keyword occurrences and
     * multiplies them by user field weights.
     * <code>
     * weight = 0
     * foreach ( field in matching_fields )
     * weight += num_keyword_occurrences ( field )
     * </code>
     *
     * Note that it counts all occurrences, and not the unique keywords.
     * Therefore 3 occurrences of just 1 matching keyword will contribute
     * exactly as much as 1 occurrence of 3 different keywords.
     *
     * @type string
     */
    const SPH_RANK_WORDCOUNT = 'SPH_RANK_WORDCOUNT';

    /**
     * SPH_RANK_FIELDMASK ranker returns a bit mask of matched fields.
     * <code>
     * weight = 0
     * foreach ( field in matching_fields )
     * set_bit ( weight, index_of ( field ) )
     * // or in other words, weight |= ( 1 << index_of ( field ) )
     * </code>
     *
     * The other five rankers are somewhat more complicated and mostly rely on
     * phrase proximity.
     *
     * @since version 0.9.9-rc2
     */
    const SPH_RANK_FIELDMASK = 'SPH_RANK_FIELDMASK';

    /**
     * 4) SPH_RANK_PROXIMITY, the default ranker in SPH_MATCH_ALL legacy mode,
     * simply passes the phrase proximity for a weight:
     *
     * <code>
     * weight = doc_phrase_weight
     * </code>
     *
     * By the definition of phrase weight, when documents do match the query but
     * no sequence of two keywords matches, all such documents will receive a
     * weight of 1. That, clearly, isn’t differentiating the results much so
     * using PROXIMITY_BM25 ranker instead is advised. The associated searching
     * performance impact should be negligible.
     *
     * @since  version 0.9.9-rc1
     *
     */
    const SPH_RANK_PROXIMITY = 'SPH_RANK_PROXIMITY';

    /**
     * SPH_RANK_MATCHANY ranker, used to emulate legacy MATCH_ANY mode,
     * combines phrase proximity and the number of matched keywords so that,
     * with default per-field weights,
     *
     * a) longer sub-phrase match
     * (aka bigger phrase proximity) in any field would rank higher,
     *
     * and b) in case of agreeing phrase proximity,
     * document with more matched unique keywords would rank higher.
     *
     * In other words, we look at max sub-phrase match length
     * first, and a number of unique matched keywords second.
     *
     * In pseudo-code,
     * <code>
     * k = 0
     * foreach ( field in all_fields )
     *     k += user_weight ( field ) * num_keywords ( query )
     *
     * weight = 0
     * foreach ( field in matching_fields ) {
     *     field_phrase_weight = max_common_subsequence_length ( query, field )
     *     field_rank = ( field_phrase_weight * k + num_matching_keywords ( field ))
     *     weight += user_weight ( field ) * field_rank
     * }
     * </code>
     *
     * It does not use BM25 at all because legacy mode did not use it and we
     * need to stay compatible.
     *
     * @since  version 0.9.9-rc1
     *
     */
    const SPH_RANK_MATCHANY = 'SPH_RANK_MATCHANY';

    /**
     * Mode de classement statistique, qui utilise le classement BM25 uniquement
     *  (similaire à celui de nombreux autres moteurs de recherche en texte intégral).
     *  Ce mode est plus rapide, mais peut conduire à des résultats de piétre
     *  qualité sur les requêtes qui requiérent plus d'un mot clé.
     *
     * SPH_RANK_BM25 ranker sums user weights of the matched fields and BM25.
     * <code>
     * field_weights = 0
     * foreach ( field in matching_fields )
     * field_weights += user_weight ( field )
     * weight = field_weights*1000 + integer(doc_bm25*999)
     * </code>
     *
     * Almost like PROXIMITY_BM25 ranker, except that user weights are not
     * multiplied by per-field phrase proximities. Not using phrase proximity
     * allows the engine to evaluate the query using document lists only, and
     * skip the processing of keyword occurrences lists. Unless your documents
     * are extremely short (think tweets, titles, etc), occurrence lists are
     * somewhat bigger than the document lists and take somewhat more time to
     * process. So BM25 is a faster ranker than any of the proximity-aware ones.
     *
     * Also, many other search systems either default to BM25 ranking, or even
     * provide it as the only option. So it might make sense to use BM25 ranker
     * when doing performance testing to make the comparison fair.
     */
    const SPH_RANK_BM25 = 'SPH_RANK_BM25';

    /**
     * SPH_RANK_SPH04 ranker further improves on PROXIMITY_BM25 ranker
     * (and introduces numbers instead of meaningful names,
     * too, because a name would be way too complicated).
     *
     * Phrase proximity is still the leading factor,
     * but, within a given phrase proximity,
     * matches in the beginning of the field are ranked higher,
     * and exact matches of the entire field are ranked highest.
     *
     * In pseudo-code,
     * <code>
     * field_weights = 0
     * foreach ( field in matching_fields ) {
     *     f = 4*max_common_subsequence_length ( query, field )
     *     if ( exact_field_match ( query, field ) )
     *         f += 3
     *     else if ( first_keyword_matches ( query, field ) )
     *         f += 2
     *     field_weights += f * user_weight ( field )
     * }
     * weight = field_weights*1000 + integer(doc_bm25*999)
     * </code>
     *
     * Thus, when querying for “Market Street”, SPH04 will basically rank a
     * document with exact “Market Street” match in one of the fields the
     * highest, followed by “Market Street Grocery” that begins the field with a
     * matching keyword, then followed by “West Market Street” that has a phrase
     * match somewhere, and then followed by all the documents that do mention
     * both keywords but not as a phrase (such as “Flea Market on 26th Street”).
     *
     * @since 1.10-beta
     */
    const SPH_RANK_SPH04 = 'SPH_RANK_SPH04';

    /**#@-*/

    /**
     * Index to use
     *
     * @var String
     */
    private $_sphinxIndex = null;

    /**
     * Query string (from user)
     *
     * @var integer
     */
    private $searchString;
    /**
     * 'all','any','phrase','boolean',extended'
     *
     * @var String
     */
    private $searchMode; //'all','any','phrase','boolean',extended'
    /**
     * 'all','any','phrase','boolean',extended'
     *
     * @var String
     */
    private $rankMode; // self:SPH_RANK_*

    /**
     * list of ordering column
     *
     * @var array
     */
    private $_arrSearchSortColumnOrder;
    /**
     * array containing min and max of range wanted for some attribute
     *
     * @var array
     */
    private $_arrSearchInRangeColumnMinMax;
    /**
     * array containing min and max of range unwanted for some attribute
     *
     * @var array
     */
    private $_arrSearchOutRangeColumnMinMax;
    /**
     * containing list of  wanted values for some attribute
     *
     * @var array
     */
    private $_arrSearchInFilterColumnValues;
    /**
     * containing list of  unwanted values for some attribute
     *
     * @var array
     */
    private $_arrSearchOutFilterColumnValues;
    /**
     * Max Matches
     *
     * It's not like limit of mysql.
     *
     * Maximum amount of matches that the daemon keeps in RAM for each index and can return to the client.
     *
     * Optional, default is 10000
     *
     * @var integer
     */
    private $_intMaxMatches;

    /**
     *
     * @var integer
     */
    private $_intCutOff;
    /**
     * the query string
     *
     * @var string
     */
    /**
     * rank of first subset items
     *
     * @var integer
     */
    private $_intOffset = 0;
    /**
     * Count of item to return in subset result
     *
     * @var integer
     */
    private $_intLimit;
    /**
     * Comment to include in query
     *
     * @var string
     */
    private $_strComment = null;
    // private Output

    private $_arrIndexweights; ///< per-index weights
    private $_fieldweights; ///< per-field-name weights

    /**
     * @var unknown_type
     */
    private $_query;
    private $_weights; ///< per-field weights (default is 1 for all fields)
    private $_sort; ///< match sorting mode (default is SPH_SORT_RELEVANCE)
    private $_sortby; ///< attribute to sort by (defualt is "")
    private $_groupby; ///< group-by attribute name
    private $_groupfunc; ///< group-by function (to pre-process group-by attribute value with)
    private $_groupsort; ///< group-by sorting clause (to sort groups in result set with)
    private $_groupdistinct;///< group-by count-distinct attribute
    private $_intMaxQueryTime; ///< max query time, milliseconds (default is 0, do not limit)
    private $config = null;
    public function __construct() {
    }

    public function getSearchMode() {
        return $this->searchMode;
    }

    /**
     * @return the $rankMode
     */
    public function getRankMode() {
        assert( in_array($this->rankMode, array( self::SPH_RANK_BM25,
                                                 self::SPH_RANK_NONE,
                                                 self::SPH_RANK_PROXIMITY_BM25,
                                                 self::SPH_RANK_PROXIMITY,
                                                 self::SPH_RANK_FIELDMASK,
                                                 self::SPH_RANK_MATCHANY,
                                                 self::SPH_RANK_SPH04,
                                                 self::SPH_RANK_WORDCOUNT)));
        return $this->rankMode;
    }

    /**
     * @param string $rankMode
     */
    public function setRankMode($rankMode = self::SPH_RANK_PROXIMITY_BM25) {
        assert( in_array($rankMode, array( self::SPH_RANK_BM25,
                                           self::SPH_RANK_NONE,
                                           self::SPH_RANK_PROXIMITY_BM25,
                                           self::SPH_RANK_PROXIMITY,
                                           self::SPH_RANK_FIELDMASK,
                                           self::SPH_RANK_MATCHANY,
                                           self::SPH_RANK_SPH04,
                                           self::SPH_RANK_WORDCOUNT)));
        $this->rankMode = $rankMode;
        return $this;
    }

    /// set maximum query time, in milliseconds, per-index
    /// integer, 0 means "do not limit"
    function SetMaxQueryTime($int_p_maxQueryTime) {
        assert(is_int($int_p_maxQueryTime));
        assert($int_p_maxQueryTime >= 0);
        $this->_intMaxQueryTime = $int_p_maxQueryTime;
    }

    /**
     * @return the max querytime
     */
    public function getMaxQueryTime() {
        return $this->_intMaxQueryTime;
    }
    /**
     * @param field_type $int_p_connectTimeout
     */
    public function setConnectTimeout($int_p_connectTimeout) {
        assert(is_int($int_p_connectTimeout));
        assert($int_p_connectTimeout >= 0);

        $this->_connectTimeout = $int_p_connectTimeout;
    }

    /**
     * @return the $_connectTimeout
     */
    public function getConnectTimeout() {
        return $this->_connectTimeout;
    }

    /**
     * @param unknown_type $sphinxIndex
     */
    public function setSphinxIndex($strSphinxIndex = null) {
        assert(is_null($strSphinxIndex) or is_string($strSphinxIndex));

        $this->_sphinxIndex = $strSphinxIndex;
        return $this;
    }

    /**
     * @return the $sphinxIndex
     */
    public function getSphinxIndexes() {
        return $this->_sphinxIndex;
    }

    /// bind per-field weights by name
    function SetFieldWeights($arrFieldWeights) {
        assert(is_array($arrFieldWeights));
        foreach ($arrFieldWeights as $strFieldname => $intFieldWeight) {
            assert(is_string($strFieldname));// PHP5.4.8, var_export($strFieldname,1) . ' devrait être une chaine');
            assert(is_int($intFieldWeight));// PHP5.4.8, var_export($intFieldWeight,1) . ' devrait être un entier');
        }
        $this->_fieldweights = $arrFieldWeights;
    }

    // / bind per-field weights by name
    function addFieldWeight($strFieldName, $intFieldWeight) {
        assert(is_string($strFieldName));// PHP5.4.8, var_export($strFieldName,1) . ' devrait être une chaine');
        assert(is_int($intFieldWeight));// PHP5.4.8, var_export($intFieldWeight,1) . ' devrait être un entier');
        $this->_fieldweights[$strFieldName] = $intFieldWeight;
    }

    /// bind per-index weights by name
    function SetIndexWeights($arrIndexWeights) {
        assert(is_array($arrIndexWeights));
        foreach ($arrIndexWeights as $strIndexName => $intIndexWeight) {
            assert(is_string($strIndexName));// PHP5.4.8, var_export($strIndexName,1) . ' devrait être une chaine');
            assert(is_int($intIndexWeight));// PHP5.4.8, var_export($intIndexWeight,1) . ' devrait être un entier');
        }
        $this->_arrIndexweights = $arrIndexWeights;
    }

    /// bind per-index weights by name
    function addIndexWeight($index, $weight) {
        assert(is_string($index));// PHP5.4.8, var_export($index,1) . ' devrait être une chaine');
        assert(is_int($weight));// PHP5.4.8, var_export($weight,1) . ' devrait être un entier');
        $this->_arrIndexweights[$index] = $weight;
    }

    //                                               _        _
    //          __ _ _   _  ___ _ __ _   _   ___| |_ _ __(_)_ __   __ _
    //         / _` | | | |/ _ \ '__| | | | / __| __| '__| | '_ \ / _` |
    //        | (_| | |_| |  __/ |  | |_| | \__ \ |_| |  | | | | | (_| |
    //         \__, |\__,_|\___|_|   \__, | |___/\__|_|  |_|_| |_|\__, |
    //            |_|                |___/                        |___/
    //
    /**
     * Force le mode de recherche
     * @param string $searchMode
     * @return Search_Sphinx_Query
     */
    public function setSearchMode($searchMode) {

        assert(
                        in_array($searchMode,
                                        array(self::SEARCH_MODE_ALL, self::SEARCH_MODE_ANY, self::SEARCH_MODE_BOOL,
                                                        self::SEARCH_MODE_EXTENDED, self::SEARCH_MODE_EXTENDED2,
                                                        self::SEARCH_MODE_FULLSCAN, self::SEARCH_MODE_PHRASE)));

        $this->searchMode = $searchMode;
        return $this;
    }

    /**
     * Transforme la $searchString  venant de any ou phrase  et une chainea fonction équivalente pour le mode extended.
     *
     * @param string $searchString
     */
    public function extendizeSearchstring($searchString) {
        if ($this->searchMode == self::SEARCH_MODE_PHRASE) {
            $searchString = strtr($searchString, '@', ' ');
            $searchString = '"' . $searchString . '"';
        } else if ($this->searchMode == self::SEARCH_MODE_ANY) {
            $searchString = strtr($searchString, '@', ' ');
            $arrWords = explode(' ', $searchString);
            $searchString = '"' . implode('"|"', $arrWords) . '"';
        } else if ($this->searchMode == self::SEARCH_MODE_ALL) {
            $searchString = strtr($searchString, '@', ' ');
            $arrWords = explode(' ', $searchString);
            $searchString = '"' . implode('" "', $arrWords) . '"';
        }
        return $searchString;
    }

    /**
     *
     * @param string $searchString
     * @return Search_Sphinx_Query
     *
     * @todo voir ce qu'on peut retirer et expliquer le reste
     */
    public function setSearchString($searchString) {
        $this->searchString = $searchString;
        return $this;
    }

    //          __ _ _ _
    //         / _(_) | |_ ___ _ __ ___
    //        | |_| | | __/ _ \ '__/ __|
    //        |  _| | | ||  __/ |  \__ \
    //        |_| |_|_|\__\___|_|  |___/
    //

    /**

     * Enter description here...
     *
     * @param string $searchInRangeColumn column name
     * @param integer $searchInRangeMin
     * @param integer $searchInRangeMax
     * @return Search_Sphinx_Query
     */
    public function addInRangeColumnMinMax($searchInRangeColumn, $searchInRangeMin, $searchInRangeMax) {
        $this->_arrSearchInRangeColumnMinMax[$searchInRangeColumn] = $searchInRangeColumn . ',' . $searchInRangeMin . ','
                        . $searchInRangeMax;
        return $this;
    }

    /**

     * Enter description here...
     *
     * @param string $searchInRangeColumn column name
     * @param integer $searchInRangeMax
     * @return Search_Sphinx_Query
     */
    public function addLowerThan($searchInRangeColumn, $searchInRangeMax) {
        $this->_arrSearchInRangeColumnMinMax[$searchInRangeColumn] = $searchInRangeColumn . ',' . 0 . ',' . $searchInRangeMax;
        return $this;
    }

    /**
     * Enter description here...
     *
     * @param string $searchOutRangeColumn column name
     * @param integer $searchOutRangeMin
     * @param integer $searchOutRangeMax
     * @return Search_Sphinx_Query
     */
    public function addOutRangeColumnMinMax($searchOutRangeColumn, $searchOutRangeMin, $searchOutRangeMax) {
        $this->_arrSearchOutRangeColumnMinMax[$searchOutRangeColumn] = $searchOutRangeColumn . ',' . $searchOutRangeMin . ','
                        . $searchOutRangeMax;
        return $this;
    }

    /**

     * Enter description here...
     *
     * @param string $searchInRangeColumn column name
     * @param integer $searchInRangeMax
     * @return Search_Sphinx_Query
     */
    public function addGreaterThan($searchOutRangeColumn, $searchOutRangeMin) {
        $this->_arrSearchOutRangeColumnMinMax[$searchOutRangeColumn] = $searchOutRangeColumn . ',' . 0 . ',' . $searchOutRangeMin;
        return $this;
    }

    /**
     * filter row on list of value in a specific attribute
     *
     * @param string $searchInFilterColumn column name
     * @param array $arrSearchInFilterValues
     * @return Search_Sphinx_Query
     */
    public function addInFilterColumnValues($searchInFilterColumn, $arrSearchInFilterValues) {
        // use $searchInFilterColumn to avoid several set of same filter !
        assert(is_string($searchInFilterColumn));// PHP5.4.8, var_export($searchInFilterColumn,1) . ' devrait être une chaine');
        assert(is_array($arrSearchInFilterValues));// PHP5.4.8, var_export($arrSearchInFilterValues,1) . ' devrait être un array');
        if (!empty($arrSearchInFilterValues)) {
            $this->_arrSearchInFilterColumnValues[$searchInFilterColumn] = 'filter=' . $searchInFilterColumn . ','
                            . implode(',', $arrSearchInFilterValues);
        }
        return $this;
    }
    /**
     * Set a List of value to exclude
     *
     * @param string $searchOutFilterColumn column name
     * @param array $arrSearchOutFilterValues value wich cause the reject of row
     * @return self
     */
    public function addOutFilterColumnValues($searchOutFilterColumn, $arrSearchOutFilterValues) {

        // use $searchOutFilterColumn to avoid several set of same filter !

        if (!empty($arrSearchOutFilterValues)) {
            $this->_arrSearchOutFilterColumnValues[$searchOutFilterColumn] = '!filter=' . $searchOutFilterColumn . ','
                            . implode(',', $arrSearchOutFilterValues);
        }
        return $this;
    }

    // . . . . . . . .  _ _ . . . . . _ _ . . . ._   _
    // . . . . . . . . | (_)_ __ ___ (_) |_ __ _| |_(_) ___  _ __  ___
    // . . . . . . . . | | | '_ ` _ \| | __/ _` | __| |/ _ \| '_ \/ __|
    // . . . . . . . . | | | | | | | | | || (_| | |_| | (_) | | | \__ \
    // . . . . . . . . |_|_|_| |_| |_|_|\__\__,_|\__|_|\___/|_| |_|___/
    //

    /**
     * to use pagination on sphinx level.
     * Max row wanted
     *
     * @param integer $intSearchLimit
     */
    public function setLimits($int_p_limit, $int_p_Offset = 0) {
        assert(is_int($int_p_Offset));// PHP5.4.8, var_export($int_p_Offset,1) . ' devrait être un entier');
        assert(is_int($int_p_limit));// PHP5.4.8, var_export($int_p_limit,1) . ' devrait être un entier');
        assert($int_p_Offset >= 0);// PHP5.4.8, var_export($int_p_Offset,1) . ' devrait être positif');
        assert($int_p_limit > 0);// PHP5.4.8, var_export($int_p_limit,1) . ' devrait être positif');

        $this->_intLimit = (int) $int_p_limit;
        $this->_intOffset = (int) $int_p_Offset;
        return $this;
    }

    /**
     * @return the $intSearchLimit
     */
    public function getLimit() {
        return (int) $this->_intLimit;
    }
    /**
     * To use pagination on sphinx level
     * Shift the first returned element
     *
     * @param integer $int_p_Offset
     */
    public function setOffset($int_p_Offset) {
        $this->_intOffset = (int) $int_p_Offset;
        return $this;
    }
    /**
     * @return the $intSearchOffset
     */
    public function getOffset() {
        return (int) $this->_intOffset;
    }

    /**
     * @param integer $int_p_Cutoff
     */
    public function setCutOff($int_p_Cutoff) {

        $this->_intCutOff = $int_p_Cutoff;
        return $this;
    }
    /**
     * @return the Max Matches
     */
    public function getCutOff() {
        return (int) $this->_intCutOff;
    }

    /**
     * @param integer $int_p_MaxMatches
     */
    public function setMaxMatches($int_p_MaxMatches) {

        $this->_intMaxMatches = $int_p_MaxMatches;
        return $this;
    }
    /**
     * @return the Max Matches
     */
    public function getMaxMatches() {
        return (int) $this->_intMaxMatches;
    }

    //
    //                        _   _
    //         ___  ___  _ __| |_(_)_ __   __ _
    //        / __|/ _ \| '__| __| | '_ \ / _` |
    //        \__ \ (_) | |  | |_| | | | | (_| |
    //        |___/\___/|_|   \__|_|_| |_|\__, |
    //                                    |___/
    //

    /**
     * add sorting
     *
     * @param string $searchSortColumn column name
     * @param string $searchSortOrder asc or desc
     * @return $this
     */
    public function addSortColumnOrder($searchSortColumn, $searchSortOrder = 'asc') {
        $this->_arrSearchSortColumnOrder[] = $searchSortColumn . ' ' . strtolower($searchSortOrder);
        return $this;
    }

    /**
     * Set sorting for result list (not for group by)
     *
     * @param string $mode use a constant self::SPH_SORT_*
     * @param string $sortby asc , desc or empty  (needed by some modes)
     */
    public function setSortMode($mode, $sortby = '') {
        assert(is_string($sortby));// PHP5.4.8, var_export($sortby,1) . ' devrait être une chaine');
        assert(
                        in_array($mode,
                                        array(self::SPH_SORT_RELEVANCE, self::SPH_SORT_ATTR_DESC, self::SPH_SORT_ATTR_ASC,
                                                        self::SPH_SORT_TIME_SEGMENTS, self::SPH_SORT_EXTENDED, self::SPH_SORT_EXPR)));// PHP5.4.8, var_export($mode,1) . ' devrait être une chaine parmis les constantes self::SPH_SORT_*');
        $this->_sort = $mode;
        $this->_sortby = $sortby;
        return $this;
    }

    //                                     _
    //          __ _ _ __ ___  _   _ _ __ (_)_ __   __ _
    //         / _` | '__/ _ \| | | | '_ \| | '_ \ / _` |
    //        | (_| | | | (_) | |_| | |_) | | | | | (_| |
    //         \__, |_|  \___/ \__,_| .__/|_|_| |_|\__, |
    //         |___/                |_|            |___/
    //

    /*
    public function setGroupBy (
        string $attribute ,
        int $func  ,
        $groupsort = '@group desc'  ) {

        if ( $groupsort != '@group desc' ) {
            trigger_error('groupsort param not yet implemented');
        }

    }
     */

    /**
     * Set grouping attribute and function
     *
     * @param string $attribute a valid attribute of the index
     * @param string $mode type of grouping choose one self::SPH_GROUPBY_* const
     * @param string $groupsort
     */
    function setGroupBy($attribute, $mode, $groupsort = "@group desc") {
        assert(is_string($attribute));// PHP5.4.8 , var_export($attribute,1) . ' devrait être une chaine');
        assert(is_string($groupsort));// PHP5.4.8, var_export($attribute,1) . ' devrait être une chaine');
        assert(
                        $mode == self::SPH_GROUPBY_DAY || $mode == self::SPH_GROUPBY_WEEK || $mode == self::SPH_GROUPBY_MONTH
                                        || $mode == self::SPH_GROUPBY_YEAR || $mode == self::SPH_GROUPBY_ATTR
                                        || $mode == self::SPH_GROUPBY_ATTRPAIR);

        $this->_groupby = $attribute;
        $this->_groupfunc = $mode;
        $this->_groupsort = $groupsort;
    }

    /// set count-distinct attribute for group-by queries
    function setGroupDistinct($attribute) {
        assert(is_string($attribute));// PHP5.4.8, var_export($attribute,1) . ' devrait être une chaine');
        $this->_groupdistinct = $attribute;
    }

    //                                                  _
    //          ___ ___  _ __ ___  _ __ ___   ___ _ __ | |_ ___
    //         / __/ _ \| '_ ` _ \| '_ ` _ \ / _ \ '_ \| __/ __|
    //        | (_| (_) | | | | | | | | | | |  __/ | | | |_\__ \
    //         \___\___/|_| |_| |_|_| |_| |_|\___|_| |_|\__|___/

    /**
     * Read the current comment
     *
     * @return the $strComment
     */
    public function getComment() {
        return $this->_strComment;
    }

    /**
     * Add a comment you can retrive in sphinx log (and in the query :)
     * @param string $strComment or null to disable
     */
    public function setComment($strComment) {
        assert(is_string((string) $strComment) || is_null($strComment));// PHP5.4.8 , var_export($strComment,1) . ' devrait être null ou une chaine');
        $this->_strComment = trim($strComment);
        return $this;
    }

    //             ____        _     _             ____
    //            / ___| _ __ | |__ (_)_ __ __  __/ ___|  ___
    //            \___ \| '_ \| '_ \| | '_ \\ \/ /\___ \ / _ \
    //             ___) | |_) | | | | | | | |>  <  ___) |  __/
    //            |____/| .__/|_| |_|_|_| |_/_/\_\|____/ \___|
    //                  |_|

    /**
     * This function build the query for sphinxSe
     *
     * @see http://sphinxsearch.com/docs/current.html#sphinxse-using
     * @return $this
     */

    /*
     *
     *
     *
     * query - query text;
    mode - matching mode. Must be one of "all", "any", "phrase", "boolean", or "extended". Default is "all";

    index - names of the indexes to search:

    ... WHERE query='test;index=test1;';
    ... WHERE query='test;index=test1,test2,test3;';

    minid, maxid - min and max document ID to match;
        weights - comma-separated list of weights to be assigned to Sphinx full-text fields:
    ... WHERE query='test;weights=1,2,3;';

    select - a string with expressions to compute (mapping to SetSelect() API call):

    ... WHERE query='test;select=2*a+3*b as myexpr;';

    host, port - remote searchd host name and TCP port, respectively:

    ... WHERE query='test;host=sphinx-test.loc;port=7312;';

    ranker - a ranking function to use with "extended" matching mode, as in SetRankingMode() API call (the only mode that supports full query syntax). Known values are "proximity_bm25", "bm25", "none", "wordcount", "proximity", "matchany", "fieldmask"; and, starting with 2.0.4-release, "expr:EXPRESSION" syntax to support expression-based ranker (where EXPRESSION should be replaced with your specific ranking formula):

    ... WHERE query='test;mode=extended;ranker=bm25;';
    ... WHERE query='test;mode=extended;ranker=expr:sum(lcs);';

    geoanchor - geodistance anchor, as in SetGeoAnchor() API call. Takes 4 parameters which are latitude and longiture attribute names, and anchor point coordinates respectively:

    ... WHERE query='test;geoanchor=latattr,lonattr,0.123,0.456';

     * */
    public function buildSphinxQuery() {

        $this->_query = $this->searchString . ';';

        if (!is_null($this->searchMode)) {
            $this->_query .= 'mode=' . $this->searchMode . ';';
        }

        # indexweights - comma-separated list of index names
        # and weights to use when searching through several indexes:
        # ... WHERE query='test;indexweights=idx_exact,2,idx_stemmed,1;';
        if (!is_null($this->_fieldweights)) {
            $this->_query .= 'fieldweights=';
            $first = true;
            foreach ($this->_fieldweights as $strIndexName => $intWeights) {
                // j'aime pas cette écriture.
                if ($first) {
                    $first = false;
                } else {
                    $this->_query .= ',';
                }
                $this->_query .= $strIndexName . ',' . $intWeights;
            }
            $this->_query .= ';';
        }

        # distinct - an attribute to compute COUNT(DISTINCT) for when doing group-by, as in SetGroupDistinct() API call:
        #... WHERE query='test;groupby=attr:country_id;distinct=site_id';

        // to write
        #range, !range - comma-separated attribute name, min and max value to match:

        # include groups from 3 to 7, inclusive
        #... WHERE query='test;range=group_id,3,7;';

        if (count($this->_arrSearchInRangeColumnMinMax) > 0) {
            foreach ($this->_arrSearchInRangeColumnMinMax as $column => $range) {
                $this->_query .= 'range=' . $range . ';';
            }
        } // There are in range attributes

        # exclude groups from 5 to 25
        #... WHERE query='test;!range=group_id,5,25;';

        if (count($this->_arrSearchOutRangeColumnMinMax) > 0) {
            // There are out range attributes
            $this->_query .= '!range=' . implode(';!range=', $this->_arrSearchOutRangeColumnMinMax) . ';';
        }

        #filter, !filter - comma-separated attribute name and a set of values to match:

        # only include groups 1, 5 and 19
        //... WHERE query='test;filter=group_id,1,5,19;';

        if (count($this->_arrSearchInFilterColumnValues) > 0) {
            // There are in range attributes
            $this->_query .= implode(';', $this->_arrSearchInFilterColumnValues) . ';';
        }

        # exclude groups 3 and 11
        //... WHERE query='test;!filter=group_id,3,11;';
        if (count($this->_arrSearchOutFilterColumnValues) > 0) {
            // There are out range attributes
            $this->_query .= implode(';', $this->_arrSearchOutFilterColumnValues) . ';';
        }

        #limit - amount of matches to retrieve from result set, default is 20;
        if ($this->getLimit() > 0) {
            $this->_query .= 'limit=' . $this->getLimit() . ';';
        }

        #offset - offset into result set, default is 0;
        if ($this->getOffset() > 0) {
            $this->_query .= 'offset=' . $this->getOffset() . ';';
        }

        #maxmatches - per-query max matches value, as in max_matches parameter to SetLimits() API call:
        #... WHERE query='test;maxmatches=2000;';
        if (!is_null($this->_intMaxMatches)) {
            $this->_query .= 'maxmatches=' . $this->getMaxMatches() . ';';
        }

        #cutoff - maximum allowed matches, as in cutoff parameter to SetLimits() API call:
        #  ... WHERE query='test;cutoff=10000;';
        if (!is_null($this->_intCutOff)) {
            $this->_query .= 'cutoff=' . $this->_intCutOff . ';';
        }

        #    maxquerytme - maximum allowed query time (in milliseconds), as in SetMaxQueryTime() API call:
        #... WHERE query='test;maxquerytime=1000;';
        if (!is_null($this->_intMaxQueryTime)) {
            $this->_query .= 'maxquerytime=' . $this->_intMaxQueryTime . ';';
        }

        # sort - match sorting mode. Must be one of "relevance", "attr_desc", "attr_asc", "time_segments", or "extended".
        # In all modes besides "relevance" attribute name (or sorting clause for "extended") is also required after a colon:
        #... WHERE query='test;sort=attr_asc:group_id';
        #... WHERE query='test;sort=extended:@weight desc, group_id asc';
        if ($this->_sort !== self::SPH_SORT_EXTENDED) {
            if (!empty($this->_sort)) {
                $this->_query .= 'sort=' . $this->_sort;
                if (!empty($this->_sortby)) {
                    $this->_query .= ':' . $this->_sortby;
                }
                $this->_query .= ';';
            }
        } else {
            // There are sorting attributes
            if (count($this->_arrSearchSortColumnOrder) > 0) {
                $this->_query .= 'sort=extended';
                $this->_query .= ':' . implode(',', $this->_arrSearchSortColumnOrder) . ';';
            }
        }

        # ranker - a ranking function to use with "extended" matching mode, as in SetRankingMode() API call (the only mode that supports full query syntax). Known values are "proximity_bm25", "bm25", "none", "wordcount", "proximity", "matchany", "fieldmask"; and, starting with 2.0.4-release, "expr:EXPRESSION" syntax to support expression-based ranker (where EXPRESSION should be replaced with your specific ranking formula):
        #
        # ... WHERE query='test;mode=extended;ranker=bm25;';
        # ... WHERE query='test;mode=extended;ranker=expr:sum(lcs);';
        if (!is_null($this->rankMode)) {
            $this->_query .= 'ranker=' . $this->rankMode . ';';
        }

        #groupby - group-by function and attribute, corresponding to SetGroupBy() API call:
        #... WHERE query='test;groupby=day:published_ts;';
        #... WHERE query='test;groupby=attr:group_id;';
        if (!is_null($this->_groupby) && !is_null($this->_groupfunc)) {
            $this->_query .= 'groupby=' . $this->_groupfunc . ':' . $this->_groupby . ';';
        }

        #groupsort - group-by sorting clause:
        #... WHERE query='test;groupsort=@count desc;';
        if (!is_null($this->_groupsort)) {
            $this->_query .= 'groupsort=' . $this->_groupsort . ';';
        }

        #comment - a string to mark this query in query log (mapping to $comment parameter in Query() API call):
        #        ... WHERE query='test;comment=marker001;';
        if (!is_null($this->_strComment)) {
            $this->_query .= 'comment=' . $this->getComment() . ';';
        }

        if (!is_null($this->getSphinxIndexes())) {
            $this->_query .= 'index=' . $this->getSphinxIndexes() . ';';
        }

        # indexweights - comma-separated list of index names
        # and weights to use when searching through several indexes:
        # ... WHERE query='test;indexweights=idx_exact,2,idx_stemmed,1;';
        if (!is_null($this->_arrIndexweights)) {
            $this->_query .= 'indexweights=';
            $first = true;
            foreach ($this->_arrIndexweights as $strIndexName => $intWeights) {
                // j'aime pas cette écriture.
                if ($first) {
                    $first = false;
                } else {
                    $this->_query .= ',';
                }
                $this->_query .= $strIndexName . ',' . $intWeights;
            }
            $this->_query .= ';';
        }

        return $this;
    }

    /**
     * @return the $query
     */
    public function getQuery() {
        return $this->_query;
    }

    /**
     * @param unknown_type $query
     *  commentée parce qu'à mon sens ca n'a pas d'intéret
     *
     *  Ca pourrait si on a un parseur inversé
    public function setQuery ($query)
    {
        $this->query = $query;
    }
     */

    //             ____           _           ____        _     _
    //            |  _ \ ___  ___| |  _   _  / ___| _ __ | |__ (_)_ __ __  __
    //            | |_) / _ \/ __| | (_) (_) \___ \| '_ \| '_ \| | '_ \\ \/ /
    //            |  __/  __/ (__| |  _   _   ___) | |_) | | | | | | | |>  <
    //            |_|   \___|\___|_| (_) (_) |____/| .__/|_| |_|_|_| |_/_/\_\
    //                                             |_|

           public function fetch() {

                if (!class_exists('SphinxClient')) {
                    return false;
                }
                $s = new SphinxClient;
                $s->setServer($this->_sphinxHost, $this->_sphinxPort);

                if (count($this->_arrSearchOutRangeColumnMinMax) > 0) {
                    foreach ($this->_arrSearchOutRangeColumnMinMax as $value) {
                        $d = explode(',',$value);
                        $s->setFilterRange ( $d[0] , $d[1] , $d[2] , true);
                    }
                }

                if (count($this->_arrSearchInRangeColumnMinMax) > 0) {
                    foreach ($this->_arrSearchInRangeColumnMinMax as $value) {
                        $d = explode(',',$value);
                        $s->setFilterRange ( $d[0] , $d[1] , $d[2] , false);
                    }
                }

                $s->setConnectTimeout ( $this->_connectTimeout );
                $s->setMaxQueryTime ( $this->$_maxquerytime );
    //            $s->setRetries ( int $this->retriesCount , int $this->retriesDelay  );
    //
                $s->setMatchMode($this->searchMode);
                $s->setFieldWeights ( $this->_fieldweights );
    //            $s->setFilter ( string $attribute , array $values [, bool $exclude = false ] );
    //            $s->setFilterFloatRange ( string $attribute , float $min , float $max [, bool $exclude = false ] );
    //            $s->setFilterRange ( string $attribute , int $min , int $max [, bool $exclude = false ] );
    //            $s->setGeoAnchor ( string $attrlat , string $attrlong , float $latitude , float $longitude );
    //            $s->setGroupBy ( string $attribute , int $func [, string $groupsort = "@group desc" ] );
    //            $s->setGroupDistinct ( string $attribute );
    //            $s->setIDRange ( int $min , int $max );
                  $s->setIndexWeights ( $this->_arrIndexweights );
    //            $s->setLimits ( int $offset , int $limit [, int $max_matches = 0 [, int $cutoff = 0 ]] );
                $s->setMatchMode ( $this->searchMode);
    //            $s->setOverride ( string $attribute , int $type , array $values );
                  $s->setRankingMode ( $this->rankMode );
    //            $s->setSelect ( string $clause );
    //            $s->setSortMode ( int $mode [, string $sortby ] );
                return $s->query($this->_query);
           }


    function __toString() {
        return $this->getQuery();
    }

}
