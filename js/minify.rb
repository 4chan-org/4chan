#!/usr/bin/env ruby
# encoding: utf-8

require 'json'
require 'net/http'

if !ARGV[0]
  puts 'usage: minify.rb filename'
  exit!
end

def minify_js
  api_url = 'http://closure-compiler.appspot.com/compile'
  filename = ARGV[0]
  
  out_dir = File.expand_path(File.dirname(__FILE__))
  out_file = out_dir + '/' + filename.split('-')[0] + '.js'
  
  puts 'Compiling...'
  
  resp = Net::HTTP.post_form(URI(api_url), {
    output_format: 'json',
    output_info: [ 'compiled_code', 'warnings', 'errors', 'statistics' ],
    compilation_level: 'SIMPLE_OPTIMIZATIONS',
    language: 'ECMASCRIPT5',
    js_code: File.open(filename, 'r:UTF-8') { |f| f.read }
  })
  
  if resp.kind_of?(Net::HTTPSuccess)
    data = JSON.parse(resp.body, symbolize_names: true)
    
    if data[:serverErrors]
      puts 'Server errors:'
      data[:serverErrors].each do |err|
        puts "  #{err[:error]} (#{err[:code]})"
      end
      exit!
    end
    
    if data[:errors]
      puts 'Errors:'
      data[:errors].each do |err|
        puts "  #{err[:error]} (#{err[:lineno]}, #{err[:charno]})"
      end
      exit!
    end
    
    if data[:warnings]
      puts 'Warnings:'
      data[:warnings].each do |err|
        puts "  #{err[:warning]} (#{err[:lineno]}, #{err[:charno]})"
      end
    end
    
    if data[:statistics]
      puts 'Statistics:'
      data[:statistics].each do |k, v|
        puts "  #{k}: #{v}"
      end
    end
    
    puts 'Writing...'
    File.open(out_file, 'wb') do |f|
      f.write(data[:compiledCode])
    end
    
  else
    puts "Bad status code: #{resp}"
  end
  
  puts 'Done'
end

minify_js
