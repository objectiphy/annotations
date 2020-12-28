<?php

declare(strict_types=1);

namespace Objectiphy\Annotations;

/**
 * @author Russell Walker <rwalker.php@gmail.com>
 * Translates between fully qualified class names and their aliases by resolving namespaces and use statements.
 */
class ClassAliasFinder
{
    private string $namespace = '';

    /**
     * Find all aliases for the given class name in the definition of the supplied reflection class.
     * @param \ReflectionClass $reflectionClass Class that contains the code we want to parse.
     * @param string $className Class name we are looking for in the code.
     * @return array
     * @throws AnnotationReaderException
     */
    public function findAliasesForClass(\ReflectionClass $reflectionClass, string $className): array
    {
        $this->namespace = '';
        $aliases = [];
        $usedNamespaces = [];
        $this->populateUsedNamespaces($reflectionClass, $usedNamespaces);
        foreach ($usedNamespaces as $useNamespace => $alias) {
            if (strpos($className, $useNamespace . '\\') === 0) {
                $aliasParts = array_filter([$alias, substr($className, strlen($useNamespace) + 1)]);
                $aliases[] = implode('\\', $aliasParts);
            }
        }

        return $aliases;
    }

    /**
     * Find the fully qualified class name for the given alias.
     * @param \ReflectionClass $reflectionClass
     * @param string $alias
     * @param bool $emptyOnFailure
     * @param bool $checkExists
     * @return string
     * @throws AnnotationReaderException
     */
    public function findClassForAlias(
        \ReflectionClass $reflectionClass, 
        string $alias, 
        bool $emptyOnFailure = true, 
        bool $checkExists = false
    ): string {
        $this->namespace = '';
        $usedNamespaces = [];
        $this->populateUsedNamespaces($reflectionClass, $usedNamespaces);
        $className = '';
        foreach ($usedNamespaces as $useNamespace => $nsAlias) {
            if (strlen($nsAlias) > 0) {
                if ($alias == $nsAlias) { //Class match
                    $className = $useNamespace;
                } elseif (strpos($alias, $nsAlias . '\\') === 0) { //Namespace match
                    $className = $useNamespace . substr($alias, strlen($nsAlias));
                }
            }
        }

        if (!$className) { //Assume same namespace as host class - in this case, always check it exists
            $className = $this->namespace . '\\' . $alias;
            $className = class_exists($className) ? $className : '';
        } else {
            $className = !$checkExists || class_exists($className) ? $className : '';
        }

        return $className ?: ($emptyOnFailure ? '' : $alias);
    }

    /**
     * Look for the namespace and any use statements.
     * @param \ReflectionClass $reflectionClass
     * @param array $usedNamespaces
     * @throws AnnotationReaderException
     */
    private function populateUsedNamespaces(\ReflectionClass $reflectionClass, array &$usedNamespaces): void
    {
        $phpContent = $this->getPreClassContent($reflectionClass);
        $tokens = token_get_all($phpContent);

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_NAMESPACE:
                        $this->namespace = $this->getNamespace($tokens, $i);
                        $usedNamespaces = array_merge($usedNamespaces, [$this->namespace => '']);
                        break;
                    case T_USE:
                        $usedNamespaces = array_merge(
                            $usedNamespaces, 
                            $this->getUseStatements($tokens, $i, $reflectionClass->getFileName())
                        );
                        break;
                }
            }
        }
    }

    /**
     * Parse the tokens from the start of the file and extract the namespace. The index should already point to the
     * namespace token.
     * @param array $tokens The PHP tokens.
     * @param int $index Array pointer for the token array.
     * @return string The namespace.
     */
    private function getNamespace(array $tokens, int &$index): string
    {
        $ns = '';
        for ($i = $index + 1; $i < count($tokens); $i++) {
            if (is_array($tokens[$i])) {
                $ns .= $tokens[$i][1];
            } else {
                return trim($ns);
            }
        }

        return '';
    }

    /**
     * Parse the tokens from the start of the file and extract all use statements.
     * @param array $tokens
     * @param int $index
     * @param string $fileName
     * @param string $prefix
     * @return array
     * @throws AnnotationReaderException
     */
    private function getUseStatements(array $tokens, int &$index, string $fileName, string $prefix = ''): array
    {
        $useStatements = [];
        $useStatement = $prefix;
        $alias = false;
        for ($i = $index + 1; $i < count($tokens); $i++) {
            $index = $i;
            if (is_array($tokens[$i])) {
                if ($tokens[$i][0] == \T_AS) {
                    //Alias follows...
                    $alias = true;
                } elseif (in_array($tokens[$i][0], [\T_NS_SEPARATOR, \T_STRING]) && strlen(trim($tokens[$i][1])) > 0) {
                    if ($alias) {
                        if (!isset($useStatements[$useStatement])) {
                            $useStatements[$useStatement] = '';
                        }
                        $useStatements[$useStatement] .= $tokens[$i][1];
                    } else {
                        $useStatement .= $tokens[$i][1];
                    }
                }
            } elseif ($tokens[$i] == ',') {
                $this->finaliseUseStatement($alias, $useStatement, $useStatements, $prefix);
                $alias = false;
            } elseif ($tokens[$i] == '{') {
                //Starting a bunch of use statements which all have the same prefix (parent namespace)
                $prefix = $useStatement;
                $useStatement = '';
                return $this->getUseStatements($tokens, $index, $fileName, $prefix);
            } elseif ($tokens[$i] == '}') {
                //End of a bunch of use statements which all have the same prefix
                if (!$prefix) {
                    $errorMessage = sprintf('Unexpected closing brace } on line %1$d of %2$s', $tokens[$i][2], $fileName);
                    throw new AnnotationReaderException($errorMessage);
                }
                $prefix = '';
                $this->finaliseUseStatement($alias, $useStatement, $useStatements, $prefix);
                return $useStatements;
            } elseif ($tokens[$i] == ';') {
                $this->finaliseUseStatement($alias, $useStatement, $useStatements, $prefix);
                return $useStatements;
            }
        }

        return [];
    }

    /**
     * Make a note of the namespace and class.
     * @param bool $alias
     * @param string $useStatement
     * @param array $useStatements
     * @param string $prefix
     */
    private function finaliseUseStatement(
        bool $alias,
        string &$useStatement,
        array &$useStatements,
        string $prefix
    ): void {
        $useStatement = trim($useStatement);
        $lastBackslashPos = strrpos($useStatement, '\\');
        $value = $lastBackslashPos === false ? $useStatement : substr($useStatement, $lastBackslashPos + 1);
        $key = substr($useStatement, 0, 1) != '\\' && class_exists($this->namespace . '\\' . $useStatement)
            ? $this->namespace . '\\' . $useStatement
            : ltrim($useStatement, '\\');
        if ($alias) { //Replace key with fully qualified class name
            $useStatements[$key] = $useStatements[$useStatement];
            if ($key != $useStatement) {
                unset($useStatements[$useStatement]);
            }
        } else {
            $useStatements[$key] = $value;
        }

        $useStatement = $prefix;
        $alias = '';
    }

    /**
     * Get the PHP code that appears before the class declaration starts (ie. namespace and use statements). We will
     * assume that the class starts on the first line that starts with the word 'class' (or the words 'abstract class'
     * or 'final class'). While technically it is possible for a line to start with the word class inside a comment
     * before the class declaration, this would still typically be after the namespace and use statements anyway. We
     * will also assume a single class per file. If the whitespace between the word 'abstract' or 'final' and the word
     * 'class' is  not a single space, it won't be found - that's ok, we'll just return the whole file contents.
     * @param \ReflectionClass $reflectionClass
     * @return string
     */
    private function getPreClassContent(\ReflectionClass $reflectionClass): string
    {
        $phpFile = $reflectionClass->getFileName();
        $phpContent = '';

        $phpFile = new \SplFileObject($phpFile);
        while (!$phpFile->eof()) {
            $line = $phpFile->fgets();
            if (substr(strtolower(ltrim($line)), 0, 6) == 'class ' 
                || substr(strtolower(ltrim($line)), 0, 15) == 'abstract class '
                || substr(strtolower(ltrim($line)), 0, 12) == 'final class ') {
                break;
            }
            $phpContent .= $line;
        }
        $phpFile = null;

        return $phpContent;
    }
}
