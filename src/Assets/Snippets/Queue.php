<?php
namespace Bolt\Assets\Snippets;

use Bolt\Assets\QueueInterface;
use Bolt\Assets\Target;
use Bolt\Controller\Zone;
use Silex\Application;

/**
 * Snippet queue processor.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 * @author Bob den Otter <bob@twokings.nl>
 */
class Queue implements QueueInterface
{
    /** @var boolean Whether to add jQuery to the HTML */
    protected $addJquery;
    /** @var Snippet[] Queue with snippets of HTML to insert. */
    protected $queue = [];

    /** @var \Silex\Application */
    private $app;
    /** @var string */
    private $matchedComments;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Insert a snippet. And by 'insert' we actually mean 'add it to the queue,
     * to be processed later'.
     *
     * @param string          $location
     * @param callable|string $callback
     * @param string          $extensionName
     * @param array|string    $extraParameters
     */
    public function add($location, $callback, $extensionName = 'core', array $extraParameters = [])
    {
        $this->queue[] = new Snippet($location, $callback, $extensionName, $extraParameters);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->queue = [];
    }

    /**
     * Extensions::processSnippetQueue()
     *
     * @param string $html
     *
     * @return string
     */
    public function process($html)
    {
        // First, gather all html <!-- comments -->, because they shouldn't be
        // considered for replacements. We use a callback, so we can fill our
        // $this->matchedComments array
        $html = preg_replace_callback('/<!--(.*)-->/Uis', [$this, 'pregCallback'], $html);

        // Process the snippets in the queue.
        foreach ($this->queue as $asset) {
            $html = $this->app['assets.injector']->inject($asset, $asset->getLocation(), $html);
        }

        // Conditionally add jQuery
        $html = $this->addJquery($html);

        // Finally, replace back ###comment### with its original comment.
        if (!empty($this->matchedComments)) {
            $html = preg_replace(array_keys($this->matchedComments), $this->matchedComments, $html, 1);
        }

        return $html;
    }

    /**
     * Get the queued snippets.
     *
     * @return \Bolt\Assets\Snippets\Snippet[]
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Insert jQuery, if it's not inserted already.
     *
     * Some of the patterns that 'match' are:
     * - jquery.js
     * - jquery.min.js
     * - jquery-latest.js
     * - jquery-latest.min.js
     * - jquery-1.8.2.min.js
     * - jquery-1.5.js
     *
     * @param string $html
     *
     * @return string HTML
     */
    protected function addJquery($html)
    {
        $addJquery = $this->app['config']->get('general/add_jquery', false);

        $zone = Zone::FRONTEND;
        /** @var RequestStack $requestStack */
        $requestStack = $this->app['request_stack'];
        if ($request = $requestStack->getCurrentRequest()) {
            $zone = Zone::get($request);
        }

        $regex = '/<script(.*)jquery(-latest|-[0-9\.]*)?(\.min)?\.js/';
        if ($addJquery && $zone === Zone::FRONTEND && !preg_match($regex, $html)) {
            $jqueryfile = $this->app['resources']->getPath('app/view/js/jquery-1.11.2.min.js');
            $asset = new Snippet(Target::BEFORE_JS, '<script src="' . $jqueryfile . '"></script>');
            $html = $this->app['assets.injector']->inject($asset, $asset->getLocation(), $html);
        }

        return $html;
    }

    /**
     * Callback method to identify comments and store them in the
     * matchedComments array.
     *
     * These will be put back after the replacements on the HTML are finished.
     *
     * @param string $c
     *
     * @return string The key under which the comment is stored
     */
    private function pregCallback($c)
    {
        $key = '###bolt-comment-' . count($this->matchedComments) . '###';
        // Add it to the array of matched comments.
        $this->matchedComments['/' . $key . '/'] = $c[0];

        return $key;
    }
}
