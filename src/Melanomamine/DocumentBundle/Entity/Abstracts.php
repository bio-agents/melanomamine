<?php

namespace Melanomamine\DocumentBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Abstracts
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="Melanomamine\DocumentBundle\Entity\AbstractsRepository")
 */
class Abstracts
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="pmid", type="string", length=255)
     */
    private $pmid;

    /**
     * @var string
     *
     * @ORM\Column(name="title", type="text")
     */
    private $title;

    /**
     * @var string
     *
     * @ORM\Column(name="text", type="text")
     */
    private $text;

    /**
     * @var float
     *
     * @ORM\Column(name="melanoma_score", type="float")
     */
    private $melanoma_score;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set pmid
     *
     * @param string $pmid
     * @return Abstracts
     */
    public function setPmid($pmid)
    {
        $this->pmid = $pmid;

        return $this;
    }

    /**
     * Get pmid
     *
     * @return string
     */
    public function getPmid()
    {
        return $this->pmid;
    }

    /**
     * Set title
     *
     * @param string $title
     * @return Abstracts
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set text
     *
     * @param string $text
     * @return Abstracts
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

     /**
     * Set melanoma_score
     *
     * @param float $melanoma_score
     * @return Abstracts
     */
    public function setMelanoma_score($melanoma_score)
    {
        $this->melanoma_score = $melanoma_score;

        return $this;
    }

    /**
     * Get melanoma_score
     *
     * @return float
     */
    public function getMelanoma_score()
    {
        return $this->melanoma_score;
    }
}
