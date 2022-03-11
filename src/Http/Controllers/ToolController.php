<?php

namespace Runline\ProfileTool\Http\Controllers;


use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\PasswordConfirmation;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Models\User as UserModel;
use App\Nova\User;
use Laravel\Nova\Nova;

class ToolController extends Controller
{
    public function profileFields(NovaRequest $request)
    {
        $user = Auth::user();

        return [
            // without required will not show red '*' on the field
            Text::make(__('Name'), 'name')
                ->rules('required', 'max:255', 'string')
                ->required(),

            Text::make(__('Username'), 'username')
                ->rules('required', 'max:255', 'unique:users,username,' . $user->id)
                ->required(),

            Text::make(__('Email'), 'email')
                ->rules('max:255', 'email')
                ->required(),

            Select::make(__('Locale'), 'locale')
                ->options([
                    'en' => 'English',
                    'id' => 'Indonesia',
                    'zh-TW' => '中文'
                ]),

            Password::make(__('Password'), 'password')
                ->rules('nullable', 'string', 'confirmed'),

            PasswordConfirmation::make(__('Password Confirmation'), 'password_confirmation'),

            Password::make(__('Current Password'), 'current_password')
                ->rules('required' )
                ->required(),
        ];
    }

    private function resolveFieldValue($fields)
    {
        return $fields->each( function (Field $field) {
            $field->value = Auth::user()->{$field->attribute};
        });
    }

    public function fields(NovaRequest $request)
    {
        $fields = new FieldCollection(array_values($this->profileFields($request)));

        return $this->resolveFieldValue($fields);
    }

    public function updateProfile(NovaRequest $request)
    {
        $this->validateInput($request);

        $this->validCurrentPassword($request);

        $model = UserModel::find(Auth::user()->id);

        [$model, $callbacks] = User::fillForUpdate($request, $model);

        $model->save();

    }

    private function validCurrentPassword(NovaRequest $request)
    {
        if (! Hash::check($request->current_password, Auth::user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('Invalid password')
            ]);
        }
    }

    private function validateInput(NovaRequest $request)
    {
        $rules = collect($this->profileFields($request))->mapWithKeys(function ($field) {
            return [$field->attribute => $field->rules];
        })->all();

        Validator::make($request->all(), $rules)->validate();
    }

    public function index()
    {
        $fields = [];

        foreach (config('nova-profile-tool.fields') as $field) {

            if (!is_null($field['value'])) {
                $field['value'] = auth()->user()->{$field['value']};
            }

            if (!is_null($field['value']) && $field['component'] == 'file-field') {
                $field['previewUrl'] = Storage::disk('public')->url($field['value']);
                $field['thumbnailUrl'] = Storage::disk('public')->url($field['value']);
            }

            $field['name'] = ucfirst(__("validation.attributes." . $field['attribute']));
            $field['indexName'] = ucfirst(__("validation.attributes." . $field['attribute']));

            $fields[] = $field;
        }

        return response()->json($fields);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function store()
    {
        $validations = config('nova-profile-tool.validations');

        request()->validate($validations);

        $fields = request()->only(array_keys($validations));

        if (empty($fields['password'])) {
            unset($fields['password']);
        } else {
            $fields['password'] = Hash::make($fields['password']);
        }

        foreach ($fields as $key => $field) {
            if ($field instanceof UploadedFile) {
                $path = $field->store('files', 'public');

                $fields[$key] = $path;
            }
        }

        auth()->user()->update($fields);

        return response()->json(__("Your profile has been updated!"));
    }
}
