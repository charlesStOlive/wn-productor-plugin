<?php namespace Waka\Productor\Classes;

class CheckAcceptedModel
{
    protected array $models = [];
    protected string $rule = '';


    public function __construct(string $rule) {
       $this->rule = $rule;
    }

    public function addModels(array $models) {
       $this->models = array_merge($models, $this->models);
    }

    public function check()
    {
        $results = [];
        foreach ($this->models as $key => $value) {
            $checked = \Str::is($this->rule, $key);
            //trace_log("check : " . $this->rule . ' : ' . $key . ' : ' . $checked);
            if ($checked) {
                $results[$key] = $value;
            }
        }
        return $results;
    }
}