<?php

namespace PbdKn\ContaoEllipseBundle\Controller\ContentElement;

use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\ContentModel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * @ContentElement("ellipse_element", category="miscellaneous", template="ce_ellipse")
 */
class EllipseController extends AbstractContentElementController
{
    protected function getResponse(ContentModel $model, Request $request): Response
    {
        return $this->render(
            $model->customTpl ?: 'ce_ellipse',
            [
                'majorAxis'   => $model->ellipse_major_axis,
                'minorAxis'   => $model->ellipse_minor_axis,
                'circleRadius'=> $model->ellipse_circle_radius,
                'pointOffset' => $model->ellipse_point_offset,
                'angleLimit'  => $model->ellipse_angle_limit,
                'stepSize'    => $model->ellipse_step_size,
            ]
        );
    }
}
