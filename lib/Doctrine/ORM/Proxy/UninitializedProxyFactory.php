<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM\Proxy;

use Doctrine\ORM\EntityManager,
    Doctrine\ORM\Mapping\ClassMetadata,
    Doctrine\ORM\Mapping\AssociationMapping;

/**
 * This factory is used to create proxy objects with special uninitialized values
 * See http://blog.juzna.cz/2011/06/lazy-loading-in-php/
 * 
 * @author Jan Dolecek <juzna.cz@gmail.com>
 */
class UninitializedProxyFactory implements ProxyFactoryInterface
{
    /** The EntityManager this factory is bound to. */
    private $_em;

    /**
     * Initializes a new instance of the <tt>ProxyFactory</tt> class that is
     * connected to the given <tt>EntityManager</tt>.
     *
     * @param EntityManager $em The EntityManager the new factory works for.
     */
    protected function __construct(EntityManager $em)
    {
        $this->_em = $em;
    }

    public static function create(EntityManager $em, $registerInitializer = true)
    {
        $ret = new static($em);
        if($registerInitializer) spl_initialize_register(array($ret, '_initialize'));
        return $ret;
    }

    public function getProxy($className, $identifier)
    {
        /** @var \Doctrine\ORM\Mapping\ClassMetadata $classMetaData */
        $classMetaData = $this->_em->getClassMetadata($className);

        $obj = $classMetaData->newInstance();

        foreach($classMetaData->reflFields as $fieldName => $fieldReflection) {
            $fieldReflection->setValue($obj, isset($identifier[$fieldName]) ? $identifier[$fieldName] : uninitialized);
        }

        return $obj;
    }

    public function generateProxyClasses(array $classes, $toDir = null)
    {
        // void
    }

    /**
     * Magic method to lazy-load uninitialized values
     * @param $obj
     * @param $propertyName
     * @return
     */
    public function _initialize($obj, $propertyName) {
        $className = get_class($obj);

        /** @var \Doctrine\ORM\Mapping\ClassMetadata $classMetaData */
        if(!$classMetaData = $this->_em->getClassMetadata($className)) return; // we don't know how to hydrate it

        // Create identifier
        $identifier = array();
        foreach($classMetaData->getIdentifierFieldNames() as $fieldName) {
            $ref = $classMetaData->reflFields[$fieldName];
            if (!$ref->isInitialized($obj)) throw new \Exception("Primary key is not initialized");
            $identifier[$fieldName] = $ref->getValue($obj);
        }

        $this->_em->getUnitOfWork()->getEntityPersister($className)->load($identifier, $obj);
    }
}
