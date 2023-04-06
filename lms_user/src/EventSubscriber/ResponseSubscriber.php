<?php

namespace Drupal\lms_user\EventSubscriber;

use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class ResponseSubscriber.
 *
 * @package Drupal\pp_transaction\EventSubscriber
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * ResponseSubscriber constructor.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   */
  public function __construct(AccountProxyInterface $current_user, RouteMatchInterface $route_match) {
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
  }

  /**
   * Redirects paths starting with multiple slashes to a single slash.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The GetResponseEvent to process.
   */
  public function onRequest(GetResponseEvent $event) {
    $lms_user_manager = \Drupal::service('lms_user.manager');
    if ($this->routeMatch->getRouteName() == 'entity.user.canonical') {
      if ($this->currentUser->isAuthenticated()) {
        $user = User::load($this->currentUser->id());
        if (!$user->hasRole('lms_admin') && !$user->hasRole('administrator')) {
          $dashboard_url = $lms_user_manager->getUrlDashboardWithLanguage($user, 'lms_user.dashboard');
          $event->setResponse(new CacheableRedirectResponse($dashboard_url));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 0];
    return $events;
  }

}
