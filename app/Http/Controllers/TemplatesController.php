<?php

namespace App\Http\Controllers;

use App\Item;
use App\Checklist;
use App\Template;
use App\History;
use Illuminate\Http\Request;
use DB;
use URL;
use Illuminate\Support\Facades\Auth;

class TemplatesController extends Controller
{

    public $url = 'api/templates/checklists';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        try {

            $templatesCount = DB::table('templates')
                         ->select(DB::raw('count(*) as total'))->first();

            $total = (int) $templatesCount->total;
            $limit  = $request->page_limit  ? (int) $request->page_limit  : 0;
            $offset = $request->page_offset ? (int) $request->page_offset : 0;
            $count = (int) $total < $limit ? 0 : ceil((int) $total / (int) $limit);

            $templates = DB::table('templates')
                    ->offset((int) $offset)
                    ->limit((int) $limit)
                    ->get();

            $showPaging  = $this->showPaging((int) $total,$limit,$offset,$this->url,$count);

            $params            = $request->all();
            $response          = [];
            $response['meta']  = ['total' => (int) $total,'count' => $count];
            $response['links'] = $showPaging;

            $data = [];
            foreach ($templates as $key => $value) {
                $templates = DB::table('templates')->select('name')
                    ->where('id', '=', $value->id)
                    ->first();

                $checklists = DB::table('checklists')->select('description','due_unit','due_interval')
                    ->where('template_id', '=', $value->id)
                    ->first();

                $items = DB::table('items')->select('description','urgency','due_unit','due_interval')
                    ->where('template_id', '=', $value->id)
                    ->get();

                $data[] = ['id' => $value->id,'name' => $templates->name,'checklists' => $checklists,'items' => $items];

            }

            $response['data']  = $data;

            return response()->json($response,200);

        } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => 'Server Error',
                    'status'  => 500,
                ], 500);
        }

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
                    // $item->type         = 'checklists';
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


                $History = new History();
                $saveLog = [
                    'loggable_type' => 'templates',
                    'action'        => 'create',
                    'value'         => $templateId,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ];
                $History->saveLog($saveLog);


                return response()->json($response,201);
            }

         } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => 'Server Error',
                    'status'  => 500,
                ], 500);
        }

    }

    public function getone(Request $request,$id)
    {
        try {
            $reqBody['templateId'] = $id;
            $validator = \Validator::make($reqBody, [
                'templateId'     => 'exists:templates,id',
            ]);

            if ($validator->fails())
            {
                //return required validation
                return response()->json([
                        'error'      => 'Not Found',
                        'status'     => 404
                        ],
                       404);
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
                    'error'    => 'Server Error',
                    'status'  => 500,
                ], 500);
        }
    }

    public function update(Request $request,$id)
    {
        $reqBody = $request->all();
        $reqBody['data']['id'] = $id;
        $reqCheckList = $reqBody['data']['checklist'];
        $reqItem      = $reqBody['data']['items'];
        $req          = $reqBody['data'];

        try {
            $tempalateValidator = \Validator::make($req, [
                'id'     => 'required|exists:templates,id',
            ]);

            $checklistValidator = \Validator::make($reqCheckList, [
                'description'     => 'required|string',
            ]);
            $itemValidator = \Validator::make($reqItem, [
                '*.description'     => 'required|string',
            ]);


            if ($tempalateValidator->fails())
            {
                //return required validation
                return response()->json([
                        'error'      => $tempalateValidator->errors(),
                        'type'       => 'template validation',
                        'status'     => 400
                        ],
                       400);
            }
            elseif ($checklistValidator->fails())
            {
                //return required validation
                return response()->json([
                        'error'      => $checklistValidator->errors(),
                        'type'       => 'checklist validation',
                        'status'     => 400
                        ],
                       400);
            }
            elseif($itemValidator->fails())
            {
                  //return required validation
                  return response()->json([
                        'error'      => $itemValidator->errors(),
                        'type'       => 'items validation',
                        'status'     => 400
                        ],
                       400);
            }
            else
            {
                $template  = Template::find($id);
                $template->name = $req['name'];
                $template->save();
                // $reqAttributes['id'] = $template->id;

                $History = new History();
                $saveLog = [
                    'loggable_type' => 'templates',
                    'action'        => 'update',
                    'value'         => $id,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ];
                $History->saveLog($saveLog);


                $checklist = Checklist::where('template_id',$id)->first();
                $checklist->description       = $reqCheckList['description'];
                $checklist->due_interval      = $reqCheckList['due_interval'];
                $checklist->due_unit          = $reqCheckList['due_unit'];
                $checklist->template_id       = $id;
                $checklist->save();


                $item      = Item::where('template_id',$id);

                foreach ($reqItem as $key => $value) {
                    $item      = Item::where(['template_id' => $id,'pos' => $key])->first();
                    $item->pos          = $key;
                    $item->description  = $value['description'];
                    $item->urgency      = $value['urgency'];
                    $item->due_interval = $value['due_interval'];
                    $item->due_unit     = $value['due_unit'];
                    $item->save();
                }

                return response()->json($reqBody,200);
            }

         } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => 'Server Error',
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
                        'error'      => 'Not Found',
                        'status'     => 404
                        ],
                       404);
            }
            else
            {
                Template::find($id)->delete();
                Checklist::where('template_id', $id)->delete();
                Item::where('template_id', $id)->delete();

                $History = new History();
                $saveLog = [
                    'loggable_type' => 'templates',
                    'action'        => 'remove',
                    'value'         => $id,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ];
                $History->saveLog($saveLog);

                return response()->json([],204);
            }

        } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => 'Server Error',
                    'status'  => 500,
                ], 500);
        }
    }

    public function assign(Request $request,$id)
    {

        $reqBody      = $request->all();
        $req          = $reqBody['data'];
        try {
            $reqBody['templateId'] = $id;
            $validator = \Validator::make($reqBody, [
                 'templateId'     => 'exists:templates,id',
            ]);

            if ($validator->fails())
            {
                //return required validation
                return response()->json([
                        'error'    => 'Not Found',
                        'status'   => 400
                        ],
                       400);
            }
            else
            {

                $data = [];
                foreach ($req as $key => $value) {
                    # code...
                    $objectId = $value['attributes']['object_id'];
                    $data[] = $objectId;
                    DB::table('checklists')->where('object_id', $objectId)
                        ->update(['object_domain' => 'deals','template_id' => $id]);
                }

                $objectIds = implode(',',$data);

                $History = new History();
                $saveLog = [
                    'loggable_type' => 'templates',
                    'action'        => 'assign object_id '.$objectIds,
                    'value'         => $id,
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ];
                $History->saveLog($saveLog);

                $checklistsCount = DB::table('checklists')
                     ->select(DB::raw('count(*) as total'))->where('template_id',$id)->first();

                $checklists = DB::table('checklists')
                        ->where('template_id',$id)
                        ->get();

                $count = count($req);
                $total = $checklistsCount->total;

                // set
                $response['meta']  = ['total' => (int) $total,'count' => $count];
                $getChecklistsData = [];

                foreach ($checklists as $key => $value) {
                    $type    = $value->type;
                    $links   = URL::to('/').'/api/checklists/'.$value->id;

                    $self    = URL::to('/').'/api/checklists/'.$value->id.'/relationships/items/';
                    $related = URL::to('/').'/api/checklists/'.$value->id.'/items/';

                    $items = DB::table('items')->select('type','id')
                    ->where('checklist_id', '=', $value->id)
                    ->get();
                    $relationship = ['items' => ['links' => ['self' => $self,'related' => $related],'data' => $items]];

                    unset($value->id);
                    unset($value->template_id);
                    unset($value->type);
                    unset($value->pos);
                    $getChecklistsData[]  = [
                                                'id' => (int) $id,
                                                'type' => $type,
                                                'attributes' => $checklists,
                                                'links' => $links,
                                                'relationship' => $relationship,
                                            ];
                }
                $response['data'] = $getChecklistsData;
                return response()->json($response,201);
            }

        } catch (\Exception $e) {
            //return error message
            return response()->json([
                    'error'    => 'Server Error',
                    'status'  => 500,
                ], 500);
        }
    }


}
