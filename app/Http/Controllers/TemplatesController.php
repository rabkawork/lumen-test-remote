<?php

namespace App\Http\Controllers;

use App\Item;
use App\Checklist;
use App\Template;
use Illuminate\Http\Request;
use DB;


class TemplatesController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }



    public function index(Request $request)
    {
        $params = $request->all();
    }

    public function create(Request $request)
    {
        $reqBody = $request->all();
        $reqAttributes = $reqBody['data']['attributes'];

        try {
            $validator = \Validator::make($reqAttributes, [
                'name'     => 'required|string',
            ]);

            if ($validator->fails()) 
            {
                //return required validation
                return response()->json([
                        'error'    => $validator->errors(), 
                        'status'     => 400
                        ],
                       400);
            }
            else
            {
                $template  = new Template();
                $checklist = new Checklist();

                $template->name = $reqAttributes['name'];
                $template->save();
                $templateId = $template->id;

                $checklist->type         = 'checklists';
                $checklist->description  = $reqAttributes['checklist']['description'];
                $checklist->due_interval = $reqAttributes['checklist']['due_interval'];
                $checklist->due_unit     = $reqAttributes['checklist']['due_unit'];
                $checklist->template_id  = $templateId;
                $checklist->save();

                $items = $reqAttributes['items'];
                foreach ($items as $key => $value) {
                    $item      = new Item();
                    $item->type         = 'checklists';
                    $item->pos          = $key;
                    $item->description  = $value['description'];
                    $item->urgency      = $value['urgency'];
                    $item->due_interval = $value['due_interval'];
                    $item->due_unit     = $value['due_unit'];
                    $item->template_id  = $templateId;
                    $item->checklist_id = $checklist->id;
                    $item->save();
                }

                $response['data']['id'] = $templateId;
                $response['data']['attributes'] = $reqAttributes;

                return response()->json($response,201);
            }

         } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => $e, 
                    'status'  => 500, 
                ], 500);
        }

    }

    public function getone(Request $request,$id)
    {   
        try {
            $reqBody['templateId'] = $id;
            $validator = \Validator::make($reqBody, [
                'templateId'     => 'required|exists:templates,id',
            ]);

            if ($validator->fails()) 
            {
                //return required validation
                return response()->json([
                        'error'    => $validator->errors(), 
                        'status'     => 400
                        ],
                       400);
            }
            else
            {

                $attributes = [];
                $templates = DB::table('templates')->select('name')
                    ->where('id', '=', $id)
                    ->first();
                
                $attributes['name'] = $templates->name;

                $checklists = DB::table('checklists')->select('description','due_unit','due_interval')
                    ->where('template_id', '=', $id)
                    ->first();

                $attributes['checklists'] = $checklists;


                $items = DB::table('items')->select('description','urgency','due_unit','due_interval')
                    ->where('template_id', '=', $id)
                    ->get();

                $attributes['items'] = $items;

                $response['data']['id']         = $id;
                $response['data']['type']       = 'templates';
                $response['data']['attributes'] = $attributes;

                return response()->json($response,200);
            }

        } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => $e, 
                    'status'  => 500, 
                ], 500);
        }
    }

    public function update(Request $request,$id)
    {
        $reqBody = $request->all();
        $reqAttributes = $reqBody['data']['attributes'];

        try {
            $validator = \Validator::make($reqAttributes, [
                'name'     => 'required|string',
            ]);

            if ($validator->fails()) 
            {
                //return required validation
                return response()->json([
                        'error'    => $validator->errors(), 
                        'status'     => 400
                        ],
                       400);
            }
            else
            {
                $template  = new Template();
                $checklist = new Checklist();

                $template->name = $reqAttributes['name'];
                $template->save();
                $reqAttributes['id'] = $template->id;

                $checklist->type              = 'checklists';
                $checklist->description       = $reqAttributes['checklist']['description'];
                $checklist->due_interval      = $reqAttributes['checklist']['due_interval'];
                $checklist->due_unit          = $reqAttributes['checklist']['due_unit'];
                $checklist->template_id       = $reqAttributes['id'];
                $checklist->save();

                $items = $reqAttributes['items'];
                foreach ($items as $key => $value) {
                    $item      = new Item();
                    $item->type         = 'checklists';
                    $item->pos          = $key;
                    $item->description  = $value['description'];
                    $item->urgency      = $value['urgency'];
                    $item->due_interval = $value['due_interval'];
                    $item->due_unit     = $value['due_unit'];
                    $item->template_id       = $reqAttributes['id'];
                    $item->checklist_id      = $checklist->id;
                    $item->save();
                }


                return response()->json($reqAttributes,201);
            }

         } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => $e, 
                    'status'  => 500, 
                ], 500);
        }


    }
    //
    public function remove($id)
    {
        try {
            $reqBody['templateId'] = $id;
            $validator = \Validator::make($reqBody, [
                 'templateId'     => 'required|exists:templates,id',
            ]);

            if ($validator->fails()) 
            {
                //return required validation
                return response()->json([
                        'error'    => $validator->errors(), 
                        'status'     => 400
                        ],
                       400);
            }
            else
            {
                Template::find($id)->delete();
                Checklist::where('template_id', $id)->delete();
                Item::where('template_id', $id)->delete();

                return response()->json('',204);
            }

        } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => $e, 
                    'status'  => 500, 
                ], 500);
        }
    }

    public function assigns(Request $request,$id)
    {

    }


}
