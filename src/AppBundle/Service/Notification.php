<?php

namespace AppBundle\Service;

use AppBundle\Event\OrderEvent;
use AppBundle\Event\OrderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Notification implements EventSubscriberInterface
{
    private $twig;
    private $mailer;
    private $twilio;
    private $ownerRecipient;
    private $sender;
    private $twilioFrom;
    private $twilioTo;

    public function __construct(\Twig_Environment $twig, \Swift_Mailer $mailer, \Services_Twilio $twilio, $ownerRecipient, $sender, $twilioFrom, $twilioTo)
    {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->twilio = $twilio;
        $this->ownerRecipient = $ownerRecipient;
        $this->sender = $sender;
        $this->twilioFrom = $twilioFrom;
        $this->twilioTo = $twilioTo;
    }

    public function onOrderCreatedEvent(OrderEvent $event)
    {
        $order = $event->getOrder();

        // notification for owner
        $this->sendMail(
            $this->ownerRecipient,
            '_mail/owner_notification.html.twig',
            array('order' => $order)
        );

        // notification for customer
        $this->sendMail(
            array($order->getEmail() => $order->getFullname()),
            '_mail/customer_notification.html.twig',
            array('order' => $order)
        );

        // notification for customer
        $this->sendSms(
            '_sms/owner_notification.html.twig',
            array('order' => $order)
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            OrderEvents::ORDER_CREATED => 'onOrderCreatedEvent'
        );
    }

    private function sendMail($recipient, $template, array $parameters = array())
    {
        $message = $this->getMessage($template, $parameters);
        $message->setFrom($this->sender);
        $message->setTo($recipient);
        $this->mailer->send($message);
    }

    private function sendSms($template, array $parameters = array())
    {
        $this->twilio->account->messages->sendMessage(
            $this->twilioFrom,
            $this->twilioTo,
            $this->twig->render($template, $parameters)
        );
    }

    public function getMessage($template, $parameters = array())
    {
        $template = $this->twig->loadTemplate($template);

        $parameters = array_merge($this->twig->getGlobals(), $parameters);

        $subject  = $template->renderBlock('subject',   $parameters);
        $bodyHtml = $template->renderBlock('body_html', $parameters);
        $bodyText = $template->renderBlock('body_text', $parameters);

        return \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setBody($bodyText, 'text/plain')
            ->addPart($bodyHtml, 'text/html')
        ;
    }
}
